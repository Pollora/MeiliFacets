<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\LabelProjection;

use Modules\MeiliFacets\Indexing\LabelProjection;

it('groups the names a visitor reads by taxonomy', function (): void {
    $labels = LabelProjection::fromTerms([
        ['name' => 'Initech', 'slug' => 'initech', 'taxonomy' => 'product_brand'],
        ['name' => 'Visage', 'slug' => 'visage', 'taxonomy' => 'product_cat'],
        ['name' => 'Soins', 'slug' => 'soins', 'taxonomy' => 'product_cat'],
    ]);

    expect($labels)->toBe(['product_brand' => ['Initech'], 'product_cat' => ['Visage', 'Soins']]);
});

it('decodes the entities wordpress stores names with', function (): void {
    $labels = LabelProjection::fromTerms([['name' => 'Laits &amp; crèmes', 'taxonomy' => 'product_cat']]);

    expect($labels)->toBe(['product_cat' => ['Laits & crèmes']]);
});

/** A document filed under a child and its parent reaches the projection with the parent twice. */
it('keeps one label per name', function (): void {
    $labels = LabelProjection::fromTerms([
        ['name' => 'Visage', 'taxonomy' => 'product_cat'],
        ['name' => 'Visage', 'taxonomy' => 'product_cat'],
    ]);

    expect($labels)->toBe(['product_cat' => ['Visage']]);
});

it('skips terms missing a name or a taxonomy', function (): void {
    $labels = LabelProjection::fromTerms([
        ['taxonomy' => 'product_brand'],
        ['name' => 'Orphan'],
        ['name' => '', 'taxonomy' => 'product_brand'],
        ['name' => 12, 'taxonomy' => 'product_brand'],
    ]);

    expect($labels)->toBe([]);
});
