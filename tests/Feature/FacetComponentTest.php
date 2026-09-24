<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakePresentation;
use Modules\MeiliFacets\View\Components\Facet as FacetComponent;
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
    use SwitchesTheSiteLocale;

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
            $panel->querySelector($this->hooked(Hook::More)),
            'The fold button sits outside the panel, so collapsing the facet leaves it behind.'
        );
    }

    #[Test]
    public function it_renders_both_fold_labels_for_the_client(): void
    {
        $button = HTMLDocument::createFromString($this->renderOne(), LIBXML_NOERROR)
            ->querySelector($this->hooked(Hook::More));

        $more = $button->querySelector($this->hooked(Hook::MoreLabel));
        $less = $button->querySelector($this->hooked(Hook::LessLabel));

        $this->assertNotNull($more, 'The fold button renders no label to reveal when it is folded.');
        $this->assertNotNull($less, 'The fold button renders no label to reveal once it is unfolded.');
        $this->assertFalse($more->hasAttribute('hidden'));
        $this->assertTrue($less->hasAttribute('hidden'));
        $this->assertNotSame(trim($more->textContent), trim($less->textContent));
    }

    /** They left the description with `R-143`: nothing else ties them to the language of the page. */
    #[Test]
    public function it_writes_the_fold_labels_in_the_language_wordpress_translates_in(): void
    {
        $html = $this->underLocales('fr', 'en_US', fn (): string => $this->renderOne());

        $this->assertStringContainsString('>Show more<', $html);
        $this->assertStringContainsString('>Show less<', $html);
        $this->assertStringNotContainsString('Voir plus', $html);
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
            Contract::ScrollAttribute->value,
            Blade::render($this->placing($this->first(), 'scroll'))
        );
    }

    #[Test]
    public function it_marks_nothing_when_the_facet_does_not_ask(): void
    {
        $this->assertStringNotContainsString(Contract::ScrollAttribute->value, $this->renderOne());
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
    public function it_counts_a_value_in_the_language_wordpress_translates_in(): void
    {
        $html = $this->underLocales('fr', 'en_US', fn (): string => $this->renderOne());

        $this->assertMatchesRegularExpression('/\\d+ results?\\b/', $html);
        $this->assertStringNotContainsString('résultat', $html);
    }

    /** R-151: named by the `<label>` that wraps it, a box reads « Aeris 16 results ». */
    #[Test]
    public function it_names_a_value_with_its_label_alone(): void
    {
        $document = $this->documentOf($this->placingEveryFacet());
        $values = $this->valuesIn($document);

        foreach ($values as $value) {
            $input = $value->querySelector($this->hooked(Hook::Input));
            $name = $document->getElementById($input->getAttribute('aria-labelledby'));

            $this->assertSame($value, $name->closest($this->hooked(Hook::FacetValue)));
            $this->assertSame($this->shownLabelOf($input), $name->textContent);
        }

        $this->assertNotEmpty($values);
    }

    /** The count stays in the `<label>`, so a click on it checks the box natively. */
    #[Test]
    public function it_describes_a_value_with_the_count_its_label_holds(): void
    {
        $document = $this->documentOf($this->placingEveryFacet());

        foreach ($this->valuesIn($document) as $value) {
            $input = $value->querySelector($this->hooked(Hook::Input));
            $count = $document->getElementById($input->getAttribute('aria-describedby'));

            $this->assertTrue($count->matches($this->hooked(Hook::Count)));
            $this->assertSame($input->closest('label'), $count->closest('label'));
            $this->assertMatchesRegularExpression('/\\d/', $count->textContent);
        }
    }

    #[Test]
    public function it_gives_every_name_and_every_count_its_own_id(): void
    {
        $ids = [];

        foreach ($this->valuesIn($this->documentOf($this->placingEveryFacet())) as $value) {
            $input = $value->querySelector($this->hooked(Hook::Input));
            $ids[] = $input->getAttribute('aria-labelledby');
            $ids[] = $input->getAttribute('aria-describedby');
        }

        $this->assertSame(array_unique($ids), $ids);
    }

    /** A view a theme copies must hold markup only: what it computes, a copy freezes (`R-151`). */
    #[Test]
    public function it_leaves_every_computation_to_the_component(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/facet.blade.php');

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('$ids->', $view);
        $this->assertStringNotContainsString('$listing->', $view);
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

    #[Test]
    public function it_marks_pills_with_one_attribute_and_nothing_else(): void
    {
        $control = Blade::render($this->placing($this->first(), 'presentation="control"'));
        $this->app->forgetScopedInstances();
        $pill = Blade::render($this->placing($this->first(), 'presentation="pill"'));

        $this->assertStringContainsString(' data-presentation="pill"', explode('>', $pill)[0]);
        $this->assertStringNotContainsString('data-presentation', $control);
        $this->assertSame($control, str_replace(' data-presentation="pill"', '', $pill));
    }

    #[Test]
    public function it_presents_a_facet_as_its_declaration_says(): void
    {
        $declared = new Facet($this->first()->taxonomy, $this->first()->label, presentation: Presentation::Pill);

        $html = Blade::render('<x-meilifacets::facet :facet="$declared" />', ['declared' => $declared]);

        $this->assertStringContainsString('data-presentation="pill"', $html);
    }

    /** A plain attribute names only the module's presentations; a theme's arrives bound. */
    #[Test]
    public function it_marks_a_theme_presentation_a_template_binds_with_its_name(): void
    {
        $html = Blade::render(
            '<x-meilifacets::facet :facet="$name" :presentation="$presentation" />',
            ['name' => $this->first()->name, 'presentation' => FakePresentation::Tile],
        );

        $this->assertStringContainsString('data-presentation="tile"', explode('>', $html)[0]);
    }

    /** R-10: the template cannot slip past the guard the declaration went through. */
    #[Test]
    public function it_refuses_pills_a_template_asks_for_on_a_single_selection_facet(): void
    {
        $single = new Facet($this->first()->taxonomy, $this->first()->label, SelectionMode::Single);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(FacetComponent::class, ['facet' => $single, 'presentation' => 'pill']);
    }

    /** The description feeds the client, which counts and filters on facets the page never showed. */
    #[Test]
    public function it_still_publishes_a_facet_a_template_placed_apart(): void
    {
        $listing = $this->listing();
        $description = $this->app->make(ListingDescription::class);

        $before = $description->of($listing);
        $listing->placeFacet($this->first()->name, Facet::class);

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
        return '['.Contract::Attribute->value.'="'.$hook->value.'"]';
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

    private function documentOf(string $rendered): HTMLDocument
    {
        return HTMLDocument::createFromString($rendered, LIBXML_NOERROR);
    }

    /**
     * @return list<Element>
     */
    private function valuesIn(HTMLDocument $document): array
    {
        return iterator_to_array($document->querySelectorAll($this->hooked(Hook::FacetValue)), false);
    }

    /** The label the listing resolved for this box, read from the listing rather than the markup. */
    private function shownLabelOf(Element $input): string
    {
        $listing = $this->listing();
        $facet = array_find(
            $listing->facets(),
            static fn (Facet $facet): bool => $listing->parameterFor($facet->taxonomy) === $input->getAttribute('name')
        );
        $value = array_find(
            $listing->valuesOf($facet),
            static fn (FacetValue $value): bool => $value->slug === $input->getAttribute('value')
        );

        return $value->label;
    }

    private function countFacets(string $rendered): int
    {
        return substr_count($rendered, 'data-meili="facet"');
    }
}
