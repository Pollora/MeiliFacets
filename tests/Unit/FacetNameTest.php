<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Facet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

enum FakeShopFacet: string
{
    case Brand = 'brand';
}

final class FacetNameTest extends TestCase
{
    /** A facet that names nothing answers to its taxonomy, so nothing has to be declared to start. */
    #[Test]
    public function it_answers_to_its_taxonomy_by_default(): void
    {
        $this->assertSame('product_brand', new Facet('product_brand', 'Brand')->name);
    }

    #[Test]
    public function it_takes_the_name_a_project_gives_it(): void
    {
        $this->assertSame('brand', new Facet('product_brand', 'Brand', name: 'brand')->name);
    }

    /** A project names its facets in an enum, so a template never writes the string itself. */
    #[Test]
    public function it_reads_the_name_off_an_enum(): void
    {
        $this->assertSame('brand', new Facet('product_brand', 'Brand', name: FakeShopFacet::Brand)->name);
    }
}
