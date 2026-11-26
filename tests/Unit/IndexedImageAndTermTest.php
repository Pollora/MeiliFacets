<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\IndexedImageAndTerm;

use Modules\MeiliFacets\Indexing\ImageFields;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use WP_Term;

beforeEach(function (): void {
    WordPress::boot();
});

it('types the size of an image and keeps the candidates only with the sizes they need', function (string|false $sizes, array $candidates): void {
    // WordPress hands the numbers back as strings when the metadata was stored that way.
    WordPress::fake('wp_get_attachment_image_src', ['https://example.test/a.jpg', '300', '200']);
    WordPress::fake('get_post_meta', 'A cat');
    WordPress::fake('wp_get_attachment_image_srcset', 'a-300.jpg 300w');
    WordPress::fake('wp_get_attachment_image_sizes', $sizes);

    expect(ImageFields::of(9, 'medium'))->toBe([
        'image_url' => 'https://example.test/a.jpg',
        'image_alt' => 'A cat',
        'image_width' => 300,
        'image_height' => 200,
        ...$candidates,
    ]);
})->with([
    'with sizes' => ['100vw', ['image_srcset' => 'a-300.jpg 300w', 'image_sizes' => 'auto, 100vw']],
    'without sizes' => [false, []],
]);

it('lists every child of a term, used or not, without loading their meta', function (): void {
    WordPress::fake('get_terms', [new WP_Term(['slug' => 'shoes'])]);

    new WordPressTermHierarchy()->childrenOf(3, 'product_cat');

    expect(WordPress::$log[array_key_last(WordPress::$log)])->toBe(['get_terms', [[
        'taxonomy' => 'product_cat',
        'parent' => 3,
        'hide_empty' => false,
        'update_term_meta_cache' => false,
    ]]]);
});
