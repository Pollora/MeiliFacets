<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\LabelProjection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LabelProjectionTest extends TestCase
{
    #[Test]
    public function it_groups_the_names_a_visitor_reads_by_taxonomy(): void
    {
        $labels = LabelProjection::fromTerms([
            ['name' => 'Lumen', 'slug' => 'lumen', 'taxonomy' => 'product_brand'],
            ['name' => 'Visage', 'slug' => 'visage', 'taxonomy' => 'product_cat'],
            ['name' => 'Soins', 'slug' => 'soins', 'taxonomy' => 'product_cat'],
        ]);

        $this->assertSame(['product_brand' => ['Lumen'], 'product_cat' => ['Visage', 'Soins']], $labels);
    }

    #[Test]
    public function it_decodes_the_entities_wordpress_stores_names_with(): void
    {
        $labels = LabelProjection::fromTerms([['name' => 'Laits &amp; crèmes', 'taxonomy' => 'product_cat']]);

        $this->assertSame(['product_cat' => ['Laits & crèmes']], $labels);
    }

    /** A document filed under a child and its parent reaches the projection with the parent twice. */
    #[Test]
    public function it_keeps_one_label_per_name(): void
    {
        $labels = LabelProjection::fromTerms([
            ['name' => 'Visage', 'taxonomy' => 'product_cat'],
            ['name' => 'Visage', 'taxonomy' => 'product_cat'],
        ]);

        $this->assertSame(['product_cat' => ['Visage']], $labels);
    }

    #[Test]
    public function it_skips_terms_missing_a_name_or_a_taxonomy(): void
    {
        $labels = LabelProjection::fromTerms([
            ['taxonomy' => 'product_brand'],
            ['name' => 'Orphan'],
            ['name' => '', 'taxonomy' => 'product_brand'],
            ['name' => 12, 'taxonomy' => 'product_brand'],
        ]);

        $this->assertSame([], $labels);
    }
}
