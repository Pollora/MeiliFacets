<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\FacetProjection;

use Modules\MeiliFacets\Indexing\FacetProjection;

it('groups a flat term list by taxonomy', function (): void {
    $facets = FacetProjection::fromTerms([
        ['slug' => 'acme', 'taxonomy' => 'product_brand'],
        ['slug' => 'large', 'taxonomy' => 'pa_size'],
        ['slug' => 'globex', 'taxonomy' => 'product_brand'],
    ]);

    expect($facets)->toBe([
        'product_brand' => ['acme', 'globex'],
        'pa_size' => ['large'],
    ]);
});

it('keeps one reindexed entry per value', function (): void {
    $facets = FacetProjection::fromTerms([
        ['slug' => 'outerwear', 'taxonomy' => 'product_cat'],
        ['slug' => 'outerwear', 'taxonomy' => 'product_cat'],
        ['slug' => 'clothing', 'taxonomy' => 'product_cat'],
    ]);

    expect($facets)->toBe(['product_cat' => ['outerwear', 'clothing']]);
});

it('skips terms missing a slug or a taxonomy', function (): void {
    $facets = FacetProjection::fromTerms([
        ['taxonomy' => 'product_brand'],
        ['slug' => 'orphan'],
        ['slug' => '', 'taxonomy' => 'product_brand'],
        ['slug' => 12, 'taxonomy' => 'product_brand'],
        ['slug' => 'acme', 'taxonomy' => 'product_brand'],
    ]);

    expect($facets)->toBe(['product_brand' => ['acme']]);
});

it('returns nothing for an untermed document', function (): void {
    expect(FacetProjection::fromTerms([]))->toBe([]);
});
