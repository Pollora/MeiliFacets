<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FacetPlacementTest extends TestCase
{
    /** What a page placed lives as long as its listing, and the suite shares one application. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    #[Test]
    public function it_shows_every_facet_when_a_template_places_none(): void
    {
        $this->assertSame(3, $this->countFacets(Blade::render('<x-meilifacets::facets />')));
    }

    /** Placing one apart must not print it twice: the group renders what is left. */
    #[Test]
    public function it_drops_from_the_group_what_a_template_placed_apart(): void
    {
        $rendered = Blade::render('<x-meilifacets::facet facet="category" /><x-meilifacets::facets />');

        $this->assertSame(1, substr_count($rendered, 'data-taxonomy="product_cat"'));
        $this->assertSame(3, $this->countFacets($rendered));
    }

    #[Test]
    public function it_names_the_facets_it_knows_when_asked_for_one_it_does_not(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/No facet named "colour".*category, brand, volume/');

        Blade::render('<x-meilifacets::facet facet="colour" />');
    }

    /** Two groups on one page would print the same inputs and ids twice. */
    #[Test]
    public function it_refuses_to_render_the_same_facet_twice(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/Facet "category" is rendered twice/');

        Blade::render('<x-meilifacets::facets /><x-meilifacets::facets />');
    }

    /** The group takes what is left, so a facet placed after it is a facet placed twice. */
    #[Test]
    public function it_refuses_a_facet_placed_after_the_group(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/Facet "category" is rendered twice/');

        Blade::render('<x-meilifacets::facets /><x-meilifacets::facet facet="category" />');
    }

    /** An empty container is markup the page carries for nothing. */
    #[Test]
    public function it_renders_no_container_when_every_facet_was_placed_apart(): void
    {
        $rendered = Blade::render(
            '<x-meilifacets::facet facet="category" /><x-meilifacets::facet facet="brand" />'
            .'<x-meilifacets::facet facet="volume" /><x-meilifacets::facets />'
        );

        $this->assertSame(3, $this->countFacets($rendered));
        $this->assertStringNotContainsString('data-meili="facets"', $rendered);
    }

    private function countFacets(string $rendered): int
    {
        return substr_count($rendered, 'data-meili="facet"');
    }
}
