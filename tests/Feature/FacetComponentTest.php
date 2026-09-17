<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\View\ElementId;
use Modules\MeiliFacets\View\ListingDescription;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * That the components reach the listing and each other. What they place, and
 * what they refuse, is `Unit\FacetPlacementTest` — on facets it declares itself.
 */
final class FacetComponentTest extends TestCase
{
    private const string NOTHING_MATCHES = 'qqxxzzww-aucun-produit-ne-correspond';

    /** What a page placed lives as long as its listing, and the suite shares one application. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    /** The suite shares one application: what is placed here would reach a later class. */
    protected function tearDown(): void
    {
        request()->query->replace([]);
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
        $panel = HTMLDocument::createFromString($this->renderOne(), LIBXML_NOERROR)
            ->querySelector('.meilifacetsFacetPanelInner');

        $this->assertNotNull($panel, 'The facet renders no panel to collapse.');
        $this->assertNotNull(
            $panel->querySelector('['.Contract::ATTRIBUTE.'="'.Hook::More->value.'"]'),
            'The fold button sits outside the panel, so collapsing the facet leaves it behind.'
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

    /** Naming a box with a count would rename it under the cursor at every filtering. */
    #[Test]
    public function it_describes_a_value_with_its_count_rather_than_naming_it(): void
    {
        $input = HTMLDocument::createFromString($this->renderOne(), LIBXML_NOERROR)
            ->querySelector('['.Contract::ATTRIBUTE.'="'.Hook::Input->value.'"]');

        $this->assertNull($input->getAttribute('aria-labelledby'));
        $this->assertSame(
            $input->getAttribute('aria-describedby'),
            $input->parentElement->querySelector('['.Contract::ATTRIBUTE.'="'.Hook::Count->value.'"]')->id
        );
    }

    #[Test]
    public function it_keeps_every_value_on_a_narrowed_page_and_hides_those_without_results(): void
    {
        $offered = count($this->listing()->valuesOf($this->first()));

        $document = $this->renderedUnder([$this->reserved(QueryParameter::Query) => self::NOTHING_MATCHES]);
        $values = $document->querySelectorAll($this->hooked(Hook::FacetValue));

        $this->assertGreaterThan(0, $offered);
        $this->assertCount($offered, $values);
        $this->assertSame([true], array_values(array_unique(array_map(static fn ($value): bool => $value->hasAttribute('hidden'), iterator_to_array($values)))));
        $this->assertTrue($document->querySelector($this->hooked(Hook::Facet))->hasAttribute('hidden'));
        $this->assertTrue($document->querySelector($this->hooked(Hook::More))->hasAttribute('hidden'));
    }

    #[Test]
    public function it_shows_a_held_value_that_has_no_result_left(): void
    {
        $held = $this->listing()->valuesOf($this->first())[0]->slug;

        $document = $this->renderedUnder([
            $this->reserved(QueryParameter::Query) => self::NOTHING_MATCHES,
            $this->app->make(UrlParameters::class)->for($this->first()->taxonomy) => $held,
        ]);
        $input = $document->querySelector('input[value="'.$held.'"]');

        $this->assertTrue($input->hasAttribute('checked'));
        $this->assertFalse($input->closest($this->hooked(Hook::FacetValue))->hasAttribute('hidden'));
        $this->assertFalse($document->querySelector($this->hooked(Hook::Facet))->hasAttribute('hidden'));
    }

    /** The description feeds the client, which counts and filters on facets the page never showed. */
    #[Test]
    public function it_still_publishes_a_facet_a_template_placed_apart(): void
    {
        $listing = $this->listing();
        $description = $this->app->make(ListingDescription::class);

        $before = $description->of($listing);
        $listing->placeApart($this->first());

        $this->assertSame(json_encode($before), json_encode($description->of($listing)));
        $this->assertCount(count($listing->facets()), $description->of($listing)['facets']);
    }

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->sole();
    }

    private function first(): Facet
    {
        return $this->listing()->facets()[0];
    }

    /** Everything the group renders, price range included. */
    private function declaredCount(): int
    {
        return count($this->listing()->filters());
    }

    /**
     * @param  array<string, string>  $query
     */
    private function renderedUnder(array $query): HTMLDocument
    {
        $this->app->forgetScopedInstances();
        request()->query->replace($query);

        return HTMLDocument::createFromString($this->renderOne(), LIBXML_NOERROR);
    }

    private function reserved(QueryParameter $parameter): string
    {
        return $this->app->make(UrlParameters::class)->reserved($parameter);
    }

    private function hooked(Hook $hook): string
    {
        return '['.Contract::ATTRIBUTE.'="'.$hook->value.'"]';
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
            fn (Placeable $filter): string => Blade::render($this->placingApart($filter)),
            $this->listing()->filters()
        ));
    }

    /** Each kind takes the component of its own kind, as `Facets` dispatches them. */
    private function placingApart(Placeable $filter): string
    {
        $component = $filter instanceof PriceFilter ? 'price' : 'facet';

        return '<x-meilifacets::'.$component.' facet="'.$filter->name.'" />';
    }

    private function countFacets(string $rendered): int
    {
        return substr_count($rendered, 'data-meili="facet"');
    }
}
