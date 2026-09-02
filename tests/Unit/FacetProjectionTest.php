<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\FacetProjection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FacetProjectionTest extends TestCase
{
    #[Test]
    public function it_groups_a_flat_term_list_by_taxonomy(): void
    {
        $facets = FacetProjection::fromTerms([
            ['slug' => 'acme', 'taxonomy' => 'product_brand'],
            ['slug' => 'large', 'taxonomy' => 'pa_size'],
            ['slug' => 'globex', 'taxonomy' => 'product_brand'],
        ]);

        $this->assertSame([
            'product_brand' => ['acme', 'globex'],
            'pa_size' => ['large'],
        ], $facets);
    }

    #[Test]
    public function it_keeps_one_reindexed_entry_per_value(): void
    {
        $facets = FacetProjection::fromTerms([
            ['slug' => 'outerwear', 'taxonomy' => 'product_cat'],
            ['slug' => 'outerwear', 'taxonomy' => 'product_cat'],
            ['slug' => 'clothing', 'taxonomy' => 'product_cat'],
        ]);

        $this->assertSame(['product_cat' => ['outerwear', 'clothing']], $facets);
    }

    #[Test]
    public function it_skips_terms_missing_a_slug_or_a_taxonomy(): void
    {
        $facets = FacetProjection::fromTerms([
            ['taxonomy' => 'product_brand'],
            ['slug' => 'orphan'],
            ['slug' => '', 'taxonomy' => 'product_brand'],
            ['slug' => 12, 'taxonomy' => 'product_brand'],
            ['slug' => 'acme', 'taxonomy' => 'product_brand'],
        ]);

        $this->assertSame(['product_brand' => ['acme']], $facets);
    }

    #[Test]
    public function it_returns_nothing_for_an_untermed_document(): void
    {
        $this->assertSame([], FacetProjection::fromTerms([]));
    }
}
