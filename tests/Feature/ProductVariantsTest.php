<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\VariantField;
use Modules\MeiliFacets\Indexing\ProductVariants;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

/** The variants the module reads off a variable product, for its card. */
final class ProductVariantsTest extends TestCase
{
    use KeepsTheIndexOut;

    private const string ATTRIBUTE = 'pa_test_volume';

    private const string HIDE_OUT_OF_STOCK = 'pre_option_woocommerce_hide_out_of_stock_items';

    private const string PROJECT_FIELD = 'volume';

    private const string PROJECT_URL = '/cart';

    private const string IMAGE_FILE = 'meilifacets-test/variant.jpg';

    /** @var list<WC_Product> */
    private array $created = [];

    /** @var list<int> */
    private array $images = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Variants are WooCommerce\'s.');
        }

        register_taxonomy(self::ATTRIBUTE, 'product', ['public' => false]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $product) {
            $product->delete(true);
        }

        foreach ($this->images as $imageId) {
            wp_delete_post($imageId, true);
        }

        unregister_taxonomy(self::ATTRIBUTE);
        remove_all_filters(self::HIDE_OUT_OF_STOCK);

        parent::tearDown();
    }

    #[Test]
    public function it_reads_each_variation_with_its_terms_its_price_and_its_stock(): void
    {
        $product = $this->variable(['15ml' => ['26', true], '400ml' => ['39', false]]);

        $variants = $this->variantsOf($product, $this->variants());

        $facets = array_column($variants, VariantField::Facets->value);

        $this->assertSame([[self::ATTRIBUTE => ['15ml']], [self::ATTRIBUTE => ['400ml']]], $facets);
        $this->assertSame([26.0, 39.0], array_column($variants, VariantField::Price->value));
        $this->assertSame([true, false], array_column($variants, VariantField::InStock->value));
    }

    #[Test]
    public function it_shows_the_variation_price_and_links_to_the_variation(): void
    {
        $fields = $this->fieldsOf($this->variable(['15ml' => ['26', true]]), $this->variants());

        $this->assertStringContainsString('26', $fields[CardField::Price->value]);
        $this->assertStringContainsString('attribute_'.self::ATTRIBUTE.'=15ml', $fields[CardField::Url->value]);
    }

    #[Test]
    public function it_lends_a_variation_without_its_own_image_none_of_the_product_one(): void
    {
        $product = $this->variable(['15ml' => ['26', true]]);
        $product->set_image_id($this->image());
        $product->save();

        $this->assertArrayNotHasKey(CardField::ImageUrl->value, $this->fieldsOf($product, $this->variants()));
    }

    #[Test]
    public function it_writes_every_image_field_of_a_variation_image_so_none_of_the_product_one_stays(): void
    {
        $product = $this->variable(['15ml' => ['26', true]], $this->image());

        $fields = $this->fieldsOf($product, $this->variants());

        $this->assertStringEndsWith('variant.jpg', (string) $fields[CardField::ImageUrl->value]);
        $this->assertSame('', $fields[CardField::ImageAlt->value]);
        $this->assertSame('', $fields[CardField::ImageSrcset->value]);
        $this->assertSame('', $fields[CardField::ImageSizes->value]);
    }

    #[Test]
    public function it_files_a_variation_under_the_ancestors_of_its_term_on_a_hierarchical_attribute(): void
    {
        register_taxonomy(self::ATTRIBUTE, 'product', ['public' => false, 'hierarchical' => true]);
        $parent = wp_insert_term('Small sizes', self::ATTRIBUTE, ['slug' => 'small-sizes']);
        $child = wp_insert_term('15ml', self::ATTRIBUTE, ['slug' => '15ml', 'parent' => $parent['term_id']]);

        try {
            $facets = $this->variantsOf($this->variable(['15ml' => ['26', true]]), $this->variants())[0][VariantField::Facets->value];
        } finally {
            wp_delete_term($child['term_id'], self::ATTRIBUTE);
            wp_delete_term($parent['term_id'], self::ATTRIBUTE);
        }

        $this->assertSame([self::ATTRIBUTE => ['15ml', 'small-sizes']], $facets);
    }

    #[Test]
    public function it_gives_a_variation_sold_for_any_value_no_term(): void
    {
        $product = $this->variable(['' => ['26', true]]);

        $this->assertSame([], $this->variantsOf($product, $this->variants())[0][VariantField::Facets->value]);
    }

    #[Test]
    public function it_leaves_out_the_variations_out_of_stock_when_the_shop_hides_them(): void
    {
        $product = $this->variable(['15ml' => ['26', true], '400ml' => ['39', false]]);

        add_filter(self::HIDE_OUT_OF_STOCK, static fn (): string => 'yes');

        $facets = array_column($this->variantsOf($product, $this->variants()), VariantField::Facets->value);

        $this->assertSame([[self::ATTRIBUTE => ['15ml']]], $facets);
    }

    #[Test]
    public function it_adds_the_project_fields_which_win_over_the_module_ones(): void
    {
        $variants = $this->app->make(ProductVariants::class, ['variantFields' => $this->projectFields()]);

        $fields = $this->fieldsOf($this->variable(['15ml' => ['26', true]]), $variants);

        $this->assertSame('15ml', $fields[self::PROJECT_FIELD]);
        $this->assertSame(self::PROJECT_URL, $fields[CardField::Url->value]);
    }

    #[Test]
    public function it_reads_no_variant_off_a_simple_product_nor_a_post(): void
    {
        $simple = new WC_Product_Simple;
        $simple->set_name('Simple, for the test');
        $simple->set_status('publish');
        $simple->set_regular_price('12');
        $simple->save();
        $this->created[] = $simple;

        $this->assertSame([], $this->variantsOf($simple, $this->variants()));
        $this->assertSame([], $this->variants()->project(new WP_Post((object) ['ID' => 0, 'post_type' => 'post'])));
    }

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
}
