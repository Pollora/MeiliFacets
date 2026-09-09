<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The suite shares one application, so a class that places facets can reach the
 * next one. This has meaning only when the whole suite runs, and only while this
 * file sorts after the classes that place: alone, it passes either way.
 */
final class ListingStateIsolationTest extends TestCase
{
    #[Test]
    public function it_reaches_a_listing_no_earlier_class_has_placed(): void
    {
        $this->assertStringContainsString('data-meili="facet"', Blade::render('<x-meilifacets::facets />'));
    }
}
