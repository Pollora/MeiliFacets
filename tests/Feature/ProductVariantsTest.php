<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\VariantField;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductVariants;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

trait ProductVariantsSupport
{
    private const string ATTRIBUTE = 'pa_test_volume';

    /** A taxonomy of the site that is not a product attribute, and a local attribute's name. */
    private const string LOCAL = 'meilifacets_test_color';

    private const string HIDE_OUT_OF_STOCK = 'pre_option_woocommerce_hide_out_of_stock_items';

    private const string PROJECT_FIELD = 'volume';

    private const string PROJECT_URL = '/cart';

    private const string IMAGE_FILE = 'meilifacets-test/variant.jpg';

    /** @var list<WC_Product> */
    private array $created = [];

    /** @var list<int> */
    private array $images = [];

    /**
     * @param  array<string, array{string, bool}>  $variations  slug to its price and whether it is in stock
     */
    private function variable(array $variations, int $variationImage = 0): WC_Product_Variable
    {
        $product = new WC_Product_Variable;
        $product->set_name('Variants, for the test');
        $product->set_status('publish');
        $product->set_attributes([$this->variationAttribute(array_keys($variations))]);
        $product->save();
        $this->created[] = $product;

        foreach ($variations as $slug => [$price, $inStock]) {
            $variation = new WC_Product_Variation;
            $variation->set_parent_id($product->get_id());
            $variation->set_status('publish');
            $variation->set_attributes([self::ATTRIBUTE => (string) $slug]);
            $variation->set_regular_price($price);
            $variation->set_stock_status($inStock ? 'instock' : 'outofstock');
            $variation->set_image_id($variationImage);
            $variation->save();
            $this->created[] = $variation;
        }

        WC_Product_Variable::sync($product->get_id());

        return new WC_Product_Variable($product->get_id());
    }

    /**
     * WooCommerce reads a variation's terms through the attributes its parent declares for variations.
     *
     * @param  list<int|string>  $slugs
     */
    private function variationAttribute(array $slugs): WC_Product_Attribute
    {
        $attribute = new WC_Product_Attribute;
        $attribute->set_name(self::ATTRIBUTE);
        $attribute->set_options(array_values(array_filter(array_map(strval(...), $slugs))));
        $attribute->set_variation(true);

        return $attribute;
    }

    /** An image WordPress holds in one size, with no alternative text. */
    private function image(): int
    {
        $imageId = wp_insert_attachment(
            ['post_mime_type' => 'image/jpeg', 'post_title' => 'Variant, for the test'],
            self::IMAGE_FILE,
        );
        wp_update_attachment_metadata($imageId, ['width' => 600, 'height' => 400, 'file' => self::IMAGE_FILE, 'sizes' => []]);

        return $this->images[] = $imageId;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(WC_Product $product, ProductVariants $variants): array
    {
        return $this->variantsOf($product, $variants)[0][VariantField::Fields->value];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variantsOf(WC_Product $product, ProductVariants $variants): array
    {
        $post = get_post($product->get_id());
        $this->assertInstanceOf(WP_Post::class, $post);

        return $variants->project($post);
    }

    private function variants(): ProductVariants
    {
        return $this->app->make(ProductVariants::class);
    }

    private function projectFields(): VariantFields
    {
        return new readonly class(self::ATTRIBUTE, self::PROJECT_FIELD, self::PROJECT_URL) implements VariantFields
        {
            public function __construct(private string $attribute, private string $field, private string $url) {}

            public function project(WC_Product_Variation $variation): array
            {
                return [
                    $this->field => $variation->get_attribute($this->attribute),
                    CardField::Url->value => $this->url,
                ];
            }
        };
    }

    /** In memory only: an attribute row would be a write to WooCommerce's own table. */
    private function declareAttribute(): void
    {
        $GLOBALS['wc_product_attributes'][self::ATTRIBUTE] = (object) ['attribute_name' => 'test_volume'];
    }
}

/** The variants the module reads off a variable product, for its card. */
uses(TestCase::class, KeepsTheIndexOut::class, ProductVariantsSupport::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('Variants are WooCommerce\'s.');
    }

    register_taxonomy(self::ATTRIBUTE, 'product', ['public' => false]);
    $this->declareAttribute();
});

afterEach(function (): void {
    foreach (array_reverse($this->created) as $product) {
        $product->delete(true);
    }

    foreach ($this->images as $imageId) {
        wp_delete_post($imageId, true);
    }

    unregister_taxonomy(self::ATTRIBUTE);
    unset($GLOBALS['wc_product_attributes'][self::ATTRIBUTE]);
    remove_all_filters(self::HIDE_OUT_OF_STOCK);
});

it('reads each variation with its terms its price and its stock', function (): void {
    $product = $this->variable(['15ml' => ['26', true], '400ml' => ['39', false]]);

    $variants = $this->variantsOf($product, $this->variants());

    $facets = array_column($variants, VariantField::Facets->value);

    expect($facets)->toBe([[self::ATTRIBUTE => ['15ml']], [self::ATTRIBUTE => ['400ml']]]);
    expect(array_column($variants, VariantField::Price->value))->toBe([26.0, 39.0]);
    expect(array_column($variants, VariantField::InStock->value))->toBe([true, false]);
});

it('reads whether each variation is on sale', function (): void {
    $product = $this->variable(['15ml' => ['26', true], '400ml' => ['39', true]]);
    $onSale = wc_get_product($product->get_children()[1]);
    $onSale->set_sale_price('30');
    $onSale->save();

    $variants = $this->variantsOf(new WC_Product_Variable($product->get_id()), $this->variants());

    expect(array_column($variants, VariantField::OnSale->value))->toBe([false, true]);
});

it('shows the variation price and links to the variation', function (): void {
    $fields = $this->fieldsOf($this->variable(['15ml' => ['26', true]]), $this->variants());

    expect($fields[CardField::Price->value])->toContain('26');
    expect($fields[CardField::Url->value])->toContain('attribute_'.self::ATTRIBUTE.'=15ml');
});

it('lends a variation without its own image none of the product one', function (): void {
    $product = $this->variable(['15ml' => ['26', true]]);
    $product->set_image_id($this->image());
    $product->save();

    $this->assertArrayNotHasKey(CardField::ImageUrl->value, $this->fieldsOf($product, $this->variants()));
});

it('writes every image field of a variation image so none of the product one stays', function (): void {
    $product = $this->variable(['15ml' => ['26', true]], $this->image());

    $fields = $this->fieldsOf($product, $this->variants());

    expect((string) $fields[CardField::ImageUrl->value])->toEndWith('variant.jpg');
    expect($fields[CardField::ImageAlt->value])->toBe('');
    expect($fields[CardField::ImageSrcset->value])->toBe('');
    expect($fields[CardField::ImageSizes->value])->toBe('');
});

it('files a variation under the ancestors of its term on a hierarchical attribute', function (): void {
    register_taxonomy(self::ATTRIBUTE, 'product', ['public' => false, 'hierarchical' => true]);
    $parent = wp_insert_term('Small sizes', self::ATTRIBUTE, ['slug' => 'small-sizes']);
    $child = wp_insert_term('15ml', self::ATTRIBUTE, ['slug' => '15ml', 'parent' => $parent['term_id']]);

    try {
        $facets = $this->variantsOf($this->variable(['15ml' => ['26', true]]), $this->variants())[0][VariantField::Facets->value];
    } finally {
        wp_delete_term($child['term_id'], self::ATTRIBUTE);
        wp_delete_term($parent['term_id'], self::ATTRIBUTE);
    }

    expect($facets)->toBe([self::ATTRIBUTE => ['15ml', 'small-sizes']]);
});

it('reads no term off a local attribute named like a taxonomy', function (): void {
    register_taxonomy(self::LOCAL, 'product', ['public' => false]);
    $product = $this->variable(['15ml' => ['26', true]]);
    $local = new WC_Product_Attribute;
    $local->set_name(self::LOCAL);
    $local->set_options(['Bleu ciel']);
    $local->set_variation(true);
    $product->set_attributes([...$product->get_attributes(), $local]);
    $product->save();
    $variation = wc_get_product($product->get_children()[0]);
    $variation->set_attributes([self::ATTRIBUTE => '15ml', self::LOCAL => 'Bleu ciel']);
    $variation->save();

    try {
        $facets = $this->variantsOf(new WC_Product_Variable($product->get_id()), $this->variants())[0][VariantField::Facets->value];
    } finally {
        unregister_taxonomy(self::LOCAL);
    }

    expect($facets)->toBe([self::ATTRIBUTE => ['15ml']]);
});

it('gives a variation sold for any value no term', function (): void {
    $product = $this->variable(['' => ['26', true]]);

    expect($this->variantsOf($product, $this->variants())[0][VariantField::Facets->value])->toBe([]);
});

it('leaves out the variations out of stock when the shop hides them', function (): void {
    $product = $this->variable(['15ml' => ['26', true], '400ml' => ['39', false]]);

    add_filter(self::HIDE_OUT_OF_STOCK, static fn (): string => 'yes');

    $facets = array_column($this->variantsOf($product, $this->variants()), VariantField::Facets->value);

    expect($facets)->toBe([[self::ATTRIBUTE => ['15ml']]]);
});

it('adds the project fields which win over the module ones', function (): void {
    $variants = $this->app->make(ProductVariants::class, ['variantFields' => $this->projectFields()]);

    $fields = $this->fieldsOf($this->variable(['15ml' => ['26', true]]), $variants);

    expect($fields[self::PROJECT_FIELD])->toBe('15ml');
    expect($fields[CardField::Url->value])->toBe(self::PROJECT_URL);
});

it('reads no variant off a simple product nor a post', function (): void {
    $simple = new WC_Product_Simple;
    $simple->set_name('Simple, for the test');
    $simple->set_status('publish');
    $simple->set_regular_price('12');
    $simple->save();
    $this->created[] = $simple;

    expect($this->variantsOf($simple, $this->variants()))->toBe([]);
    expect($this->variants()->project(new WP_Post((object) ['ID' => 0, 'post_type' => 'post'])))->toBe([]);
});
