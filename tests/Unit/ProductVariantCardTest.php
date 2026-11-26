<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ProductVariantCard;

use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductVariants;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Post;

beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    WordPress::fake('_prime_post_caches', null);
    WordPress::fake('taxonomy_is_product_attribute', false);
});

/**
 * The card fields of the first variant a variable product sells.
 *
 * @param  array<string, mixed>  $variation
 * @param  array<string, mixed>  $declared  what the project's `VariantFields` returns
 * @return array<string, mixed>
 */
function fieldsOfVariant(array $variation, array $declared = []): array
{
    $fields = new readonly class($declared) implements VariantFields
    {
        /** @param  array<string, mixed>  $declared */
        public function __construct(private array $declared) {}

        public function project(WC_Product_Variation $variation): array
        {
            return $this->declared;
        }
    };
    $sold = new WC_Product_Variation(['id' => 1, 'permalink' => 'https://example.test/v', ...$variation]);
    WordPress::fake('wc_get_product', new WC_Product_Variable(['prices' => [1 => '5'], 'variations' => [$sold]]));

    return new ProductVariants($fields, new WordPressTermHierarchy, 'medium')->project(new WP_Post)[0]['fields'];
}

it('keeps the price, the address and the project fields of a variant, and leaves the empty ones to the product', function (): void {
    $fields = fieldsOfVariant(['price_html' => '<b>5</b>'], ['badge' => 'new', 'subtitle' => '', 'swatches' => []]);

    expect($fields)->toBe(['price' => '<b>5</b>', 'url' => 'https://example.test/v', 'badge' => 'new']);
});

it('shows the image of the variation, whole, in place of the one of the product', function (): void {
    WordPress::fake('wp_get_attachment_image_src', ['https://example.test/v.jpg', 300, 200]);
    WordPress::fake('get_post_meta', 'Red');
    WordPress::fake('wp_get_attachment_image_srcset', false);
    WordPress::fake('wp_get_attachment_image_sizes', false);

    $fields = fieldsOfVariant(['image_id' => 9]);

    expect($fields)->toMatchArray([
        'image_url' => 'https://example.test/v.jpg',
        'image_alt' => 'Red',
        'image_width' => 300,
        'image_height' => 200,
        'image_srcset' => '',
        'image_sizes' => '',
    ]);
});

it('loads the images the variations own in one query, without their terms', function (): void {
    WordPress::fake('wp_get_attachment_image_src', false);
    $variations = [
        new WC_Product_Variation(['id' => 1, 'image_id' => 0]),
        new WC_Product_Variation(['id' => 2, 'image_id' => 9]),
    ];
    WordPress::fake('wc_get_product', new WC_Product_Variable(['prices' => [], 'variations' => $variations]));

    new ProductVariants(new class implements VariantFields
    {
        public function project(WC_Product_Variation $variation): array
        {
            return [];
        }
    }, new WordPressTermHierarchy, 'medium')->project(new WP_Post);

    $primed = array_values(array_filter(WordPress::$log, static fn (array $call): bool => $call[0] === '_prime_post_caches'));
    expect($primed)->toHaveCount(1)
        ->and(array_values($primed[0][1][0]))->toBe([9])
        ->and($primed[0][1][1])->toBeFalse();
});

it('indexes the prices the shop displays, taxes included when the shop shows them', function (): void {
    $product = new class(['prices' => [], 'variations' => []]) extends WC_Product_Variable
    {
        /** @var list<bool> */
        public array $asked = [];

        public function get_variation_prices(bool $for_display = false): array
        {
            $this->asked[] = $for_display;

            return parent::get_variation_prices($for_display);
        }
    };
    WordPress::fake('wc_get_product', $product);

    new ProductVariants(new class implements VariantFields
    {
        public function project(WC_Product_Variation $variation): array
        {
            return [];
        }
    }, new WordPressTermHierarchy, 'medium')->project(new WP_Post);

    expect($product->asked)->toBe([true]);
});
