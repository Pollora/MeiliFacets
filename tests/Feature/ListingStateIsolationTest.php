<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The suite shares one application, so a class that places facets can reach the
 * next one. This has meaning only when the whole suite runs, and only while this
 * file sorts after the classes that place: alone, it passes either way.
 */
uses(TestCase::class);

it('reaches a listing no earlier class has placed', function (): void {
    expect(Blade::render('<x-meilifacets::listing.facets name="products" />'))->toContain('data-meili="facet"');
});
