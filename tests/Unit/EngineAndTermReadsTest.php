<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\EngineAndTermReads;

use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use WP_Term;

beforeEach(function (): void {
    WordPress::boot();
});

/** A term no product carries still has a name to show once ticked. */
it('names the asked terms of the taxonomy, used or not, without loading their meta', function (): void {
    WordPress::fake('get_terms', [new WP_Term(['slug' => 'acme', 'name' => 'Acme'])]);

    expect(new WordPressTermLabels()->of('product_brand', ['acme']))->toBe(['acme' => 'Acme'])
        ->and(WordPress::$log[array_key_last(WordPress::$log)])->toBe(['get_terms', [[
            'taxonomy' => 'product_brand',
            'slug' => ['acme'],
            'hide_empty' => false,
            'update_term_meta_cache' => false,
        ]]]);
});

it('places a value inside a price range narrower than one unit', function (): void {
    expect(new Range(0.5, 1.0)->ratio(0.75))->toBe(0.5)
        ->and(new Range(max: 2.0)->ratio(1.0))->toBe(0.5)
        ->and(new Range(2.0, 2.0)->ratio(2.0))->toBe(0.0);
});
