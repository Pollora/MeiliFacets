<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\View\ElementId;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * That the components reach the listing and each other. What they place, and
 * what they refuse, is `Unit\FacetPlacementTest` — on facets it declares itself.
 */
final class FacetComponentTest extends TestCase
{
    /** What a page placed lives as long as its listing, and the suite shares one application. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    /** Placed facets would otherwise reach a later class, which has no reason to expect them. */
    protected function tearDown(): void
    {
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_hangs_the_hook_on_the_outermost_element(): void
    {
        $opening = explode('>', trim($this->renderOne()))[0];

        $this->assertStringStartsWith('<fieldset', $opening);
        $this->assertStringContainsString(Hook::Facet->attribute()->toHtml(), $opening);
    }

    /** A grid row collapses only if something inside it can hide its overflow. */
    #[Test]
    public function it_wraps_the_values_in_a_panel_a_theme_can_collapse(): void
    {
        $this->assertMatchesRegularExpression(
            '/<div class="meilifacetsFacetPanel"[^>]*>\s*<div class="meilifacetsFacetPanelInner">/',
            $this->renderOne()
        );
    }

    /** Without an id, a theme's disclosure has nothing to point `aria-controls` at. */
    #[Test]
    public function it_names_the_panel(): void
    {
        $listing = $this->listing();

        $this->assertStringContainsString(
            'id="'.new ElementId($listing->name())->facetPanel($this->first()->name).'"',
            $this->renderOne()
        );
    }

    /** The fold button belongs to the panel: collapsing the facet must take it along. */
    #[Test]
    public function it_keeps_the_fold_button_inside_the_panel(): void
    {
        $rendered = $this->renderOne();

        $this->assertGreaterThan(
            strpos($rendered, 'meilifacetsFacetPanelInner'),
            strpos($rendered, Hook::More->attribute()->toHtml())
        );
    }

    /** The rule is `Unit\FacetPlacementTest`; what this proves is that the component asks for it. */
    #[Test]
    public function it_places_a_named_facet_outside_the_group(): void
    {
        $rendered = Blade::render($this->placing($this->first()).'<x-meilifacets::facets />');

        $this->assertSame($this->declaredCount(), $this->countFacets($rendered));
        $this->assertSame(1, substr_count($rendered, 'data-taxonomy="'.$this->first()->taxonomy.'"'));
    }

    /** An empty container is markup the page carries for nothing. */
    #[Test]
    public function it_renders_no_container_when_every_facet_was_placed_apart(): void
    {
        config(['meilifacets.apply_mode' => 'immediate']);

        $rendered = $this->placingEveryFacet().Blade::render('<x-meilifacets::facets />');

        $this->assertSame($this->declaredCount(), $this->countFacets($rendered));
        $this->assertStringNotContainsString('data-meili="facets"', $rendered);
    }

    #[Test]
    public function it_merges_the_classes_the_caller_adds(): void
    {
        $html = Blade::render($this->placing($this->first(), 'class="lg:col-span-2"'));

        $this->assertStringContainsString('class="meilifacetsFacet lg:col-span-2"', $html);
    }

    /** A facet placed on its own brings the listing back into view like the group does. */
    #[Test]
    public function it_marks_the_facet_that_asks_to_scroll(): void
    {
        $this->assertStringContainsString(
            Contract::SCROLL_ATTRIBUTE,
            Blade::render($this->placing($this->first(), 'scroll'))
        );
    }

    #[Test]
    public function it_marks_nothing_when_the_facet_does_not_ask(): void
    {
        $this->assertStringNotContainsString(Contract::SCROLL_ATTRIBUTE, $this->renderOne());
    }

    /** A string return would have Blade compile, write and include a file that renders nothing. */
    #[Test]
    public function it_compiles_no_view_when_it_renders_nothing(): void
    {
        config(['meilifacets.apply_mode' => 'immediate']);
        $empty = config('view.compiled').'/'.hash('xxh128', '').'.blade.php';
        @unlink($empty);

        $this->placingEveryFacet();
        Blade::render('<x-meilifacets::facets />');

        $this->assertFileDoesNotExist($empty);
    }

    /** Unless it still holds the button that commits the filters. */
    #[Test]
    public function it_keeps_the_container_that_carries_the_apply_button(): void
    {
        config(['meilifacets.apply_mode' => 'submit']);

        $rendered = $this->placingEveryFacet().Blade::render('<x-meilifacets::facets />');

        $this->assertStringContainsString('data-meili="facets"', $rendered);
        $this->assertStringContainsString('data-meili="apply"', $rendered);
    }

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->sole();
    }

    private function first(): Facet
    {
        return $this->listing()->facets()[0];
    }

    private function declaredCount(): int
    {
        return count($this->listing()->facets());
    }

    private function renderOne(): string
    {
        return Blade::render($this->placing($this->first()));
    }

    private function placing(Facet $facet, string $attributes = ''): string
    {
        return '<x-meilifacets::facet facet="'.$facet->name.'" '.$attributes.' />';
    }

    private function placingEveryFacet(): string
    {
        return implode('', array_map(
            fn (Facet $facet): string => Blade::render($this->placing($facet)),
            $this->listing()->facets()
        ));
    }

    private function countFacets(string $rendered): int
    {
        return substr_count($rendered, 'data-meili="facet"');
    }
}
