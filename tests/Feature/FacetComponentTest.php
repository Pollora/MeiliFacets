<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesApplyMode;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakePresentation;
use Modules\MeiliFacets\View\Components\Listing\Facet as FacetComponent;
use Modules\MeiliFacets\View\ElementId;
use Modules\MeiliFacets\View\ListingDescription;
use Tests\TestCase;

trait FacetComponentSupport
{
    private const string NOTHING_MATCHES = 'qqxxzzww-aucun-produit-ne-correspond';

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->named(ProductListing::NAME);
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

    private function renderOne(): string
    {
        return Blade::render($this->placing($this->first()));
    }

    private function placing(Facet $facet, string $attributes = ''): string
    {
        return '<x-meilifacets::listing.facet name="products" facet="'.$facet->name.'" '.$attributes.' />';
    }

    private function placingEveryFacet(): string
    {
        return implode('', array_map(
            fn (Placeable $filter): string => Blade::render($this->placingOnItsOwn($filter)),
            $this->listing()->filters()
        ));
    }

    /** Each kind takes the component of its own kind, as `Facets` dispatches them. */
    private function placingOnItsOwn(Placeable $filter): string
    {
        $component = $filter instanceof PriceFilter ? 'price' : 'facet';

        return '<x-meilifacets::listing.'.$component.' name="products" facet="'.$filter->name.'" />';
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
        $parameters = $this->app->make(UrlParameters::class);
        $facet = array_find(
            $listing->facets(),
            static fn (Facet $facet): bool => $parameters->for($facet->taxonomy) === $input->getAttribute('name')
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

/**
 * That the components reach the listing and each other. What they place, and
 * what they refuse, is `Unit\FacetPlacementTest` — on facets it declares itself.
 */
uses(TestCase::class, FindsHooks::class, SwitchesApplyMode::class, SwitchesTheSiteLocale::class, FacetComponentSupport::class);

beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->query->replace([]);
    $this->app->forgetScopedInstances();
});

it('hangs the hook on the outermost element', function (): void {
    $opening = explode('>', trim($this->renderOne()))[0];

    expect($opening)->toStartWith('<fieldset');
    expect($opening)->toContain(Hook::Facet->attribute()->toHtml());
});

/** A grid row collapses only if something inside it can hide its overflow. */
it('wraps the values in a panel a theme can collapse', function (): void {
    expect($this->renderOne())->toMatch('/<div class="meilifacetsFacetPanel"[^>]*>\s*<div class="meilifacetsFacetPanelInner">/');
});

/** Without an id, a theme's disclosure has nothing to point `aria-controls` at. */
it('names the panel', function (): void {
    $listing = $this->listing();

    expect($this->renderOne())->toContain('id="'.new ElementId($listing->name())->facetPanel($this->first()->name).'"');
});

/** Styled without bullets, a list loses its role in Safari with VoiceOver: `role="list"` gives it back. */
it('keeps the values a list without their bullets', function (): void {
    $values = HTMLDocument::createFromString($this->renderOne(), LIBXML_NOERROR)->querySelector('.meilifacetsFacetValues');

    expect($values?->getAttribute('role'))->toBe('list');
});

/** The fold button belongs to the panel: collapsing the facet must take it along. */
it('keeps the fold button inside the panel', function (): void {
    $panel = HTMLDocument::createFromString($this->renderOne(), LIBXML_NOERROR)
        ->querySelector('.meilifacetsFacetPanelInner');

    $this->assertNotNull($panel, 'The facet renders no panel to collapse.');
    $this->assertNotNull(
        $panel->querySelector($this->hooked(Hook::More)),
        'The fold button sits outside the panel, so collapsing the facet leaves it behind.'
    );
});

it('renders both fold labels for the client', function (): void {
    $button = HTMLDocument::createFromString($this->renderOne(), LIBXML_NOERROR)
        ->querySelector($this->hooked(Hook::More));

    $more = $button->querySelector($this->hooked(Hook::MoreLabel));
    $less = $button->querySelector($this->hooked(Hook::LessLabel));

    $this->assertNotNull($more, 'The fold button renders no label to reveal when it is folded.');
    $this->assertNotNull($less, 'The fold button renders no label to reveal once it is unfolded.');
    expect($more->hasAttribute('hidden'))->toBeFalse();
    expect($less->hasAttribute('hidden'))->toBeTrue();
    expect(trim($less->textContent))->not->toBe(trim($more->textContent));
});

/** They left the description with `R-143`: nothing else ties them to the language of the page. */
it('writes the fold labels in the language wordpress translates in', function (): void {
    $html = $this->underLocales('fr', 'en_US', fn (): string => $this->renderOne());

    expect($html)->toContain('>Show more<');
    expect($html)->toContain('>Show less<');
    $this->assertStringNotContainsString('Voir plus', $html);
});

/** The rule is `Unit\FacetPlacementTest`; what this proves is that the component asks for it. */
it('places a named facet outside the group', function (): void {
    $rendered = Blade::render($this->placing($this->first()).'<x-meilifacets::listing.facets name="products" />');

    expect($this->countFacets($rendered))->toBe($this->declaredCount());
    expect(substr_count($rendered, 'data-taxonomy="'.$this->first()->taxonomy.'"'))->toBe(1);
});

/** An empty container is markup the page carries for nothing. */
it('renders no container when every facet was placed on its own', function (): void {
    $this->useApplyMode('immediate');

    $rendered = $this->placingEveryFacet().Blade::render('<x-meilifacets::listing.facets name="products" />');

    expect($this->countFacets($rendered))->toBe($this->declaredCount());
    $this->assertStringNotContainsString('data-meili="facets"', $rendered);
});

it('merges the classes the caller adds', function (): void {
    $html = Blade::render($this->placing($this->first(), 'class="lg:col-span-2"'));

    expect($html)->toContain('class="meilifacetsFacet lg:col-span-2"');
});

/** A facet placed on its own brings the listing back into view like the group does. */
it('marks the facet that asks to scroll', function (): void {
    expect(Blade::render($this->placing($this->first(), 'scroll')))->toContain(Contract::ScrollAttribute->value);
});

it('marks nothing when the facet does not ask', function (): void {
    $this->assertStringNotContainsString(Contract::ScrollAttribute->value, $this->renderOne());
});

/** A string return would have Blade compile, write and include a file that renders nothing. */
it('compiles no view when it renders nothing', function (): void {
    $this->useApplyMode('immediate');
    $empty = config('view.compiled').'/'.hash('xxh128', '').'.blade.php';
    File::delete($empty);

    $this->placingEveryFacet();
    Blade::render('<x-meilifacets::listing.facets name="products" />');

    $this->assertFileDoesNotExist($empty);
});

/** Unless it still holds the button that commits the filters. */
it('keeps the container that carries the apply button', function (): void {
    $this->useApplyMode('submit');

    $rendered = $this->placingEveryFacet().Blade::render('<x-meilifacets::listing.facets name="products" />');

    expect($rendered)->toContain('data-meili="facets"');
    expect($rendered)->toContain('data-meili="apply"');
});

test('the listing root carries the apply mode and the group does not', function (): void {
    foreach (['submit', 'immediate'] as $mode) {
        $this->useApplyMode($mode);
        $this->app->forgetScopedInstances();

        $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::listing name="products"><x-meilifacets::listing.facets /></x-meilifacets::listing>'), LIBXML_NOERROR);

        expect($document->querySelector('[data-listing]')->getAttribute('data-apply'))->toBe($mode);
        expect($document->querySelector($this->hooked(Hook::Facets))->hasAttribute('data-apply'))->toBeFalse();
    }
});

it('renders the filters and the apply button without their container when unwrapped', function (): void {
    $this->useApplyMode('submit');

    $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::listing.facets name="products" unwrapped scroll />'), LIBXML_NOERROR);
    $placed = $document->querySelectorAll($this->hooked(Hook::Facet).', '.$this->hooked(Hook::Apply));

    expect($document->querySelector($this->hooked(Hook::Facets)))->toBeNull();
    expect($placed->length)->toBeGreaterThan(1);
    expect($document->querySelectorAll('['.Contract::ScrollAttribute->value.']'))->toHaveCount($placed->length);
});

it('marks the container alone when it keeps it', function (): void {
    $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::listing.facets name="products" scroll />'), LIBXML_NOERROR);

    expect($document->querySelectorAll('['.Contract::ScrollAttribute->value.']'))->toHaveCount(1);
    expect($document->querySelector($this->hooked(Hook::Facets))->hasAttribute(Contract::ScrollAttribute->value))->toBeTrue();
});

/** Naming a box with a count would rename it under the cursor at every filtering. */
it('counts a value in the language wordpress translates in', function (): void {
    $html = $this->underLocales('fr', 'en_US', fn (): string => $this->renderOne());

    expect($html)->toMatch('/\\d+ results?\\b/');
    $this->assertStringNotContainsString('résultat', $html);
});

/** R-151: named by the `<label>` that wraps it, a box reads « Globex 16 results ». */
it('names a value with its label alone', function (): void {
    $document = $this->documentOf($this->placingEveryFacet());
    $values = $this->valuesIn($document);

    foreach ($values as $value) {
        $input = $value->querySelector($this->hooked(Hook::Input));
        $name = $document->getElementById($input->getAttribute('aria-labelledby'));

        expect($name->closest($this->hooked(Hook::FacetValue)))->toBe($value);
        expect($name->textContent)->toBe($this->shownLabelOf($input));
    }

    expect($values)->not->toBeEmpty();
});

/** The count stays in the `<label>`, so a click on it checks the box natively. */
it('describes a value with the count its label holds', function (): void {
    $document = $this->documentOf($this->placingEveryFacet());

    foreach ($this->valuesIn($document) as $value) {
        $input = $value->querySelector($this->hooked(Hook::Input));
        $count = $document->getElementById($input->getAttribute('aria-describedby'));

        expect($count->matches($this->hooked(Hook::Count)))->toBeTrue();
        expect($count->closest('label'))->toBe($input->closest('label'));
        expect($count->textContent)->toMatch('/\\d/');
    }
});

it('gives every name and every count its own id', function (): void {
    $ids = [];

    foreach ($this->valuesIn($this->documentOf($this->placingEveryFacet())) as $value) {
        $input = $value->querySelector($this->hooked(Hook::Input));
        $ids[] = $input->getAttribute('aria-labelledby');
        $ids[] = $input->getAttribute('aria-describedby');
    }

    expect($ids)->toBe(array_unique($ids));
});

/** A view a theme copies must hold markup only: what it computes, a copy freezes (`R-151`). */
it('leaves every computation to the component', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/listing/facet.blade.php');

    $this->assertStringNotContainsString('@php', $view);
    $this->assertStringNotContainsString('$ids->', $view);
    $this->assertStringNotContainsString('$listing->', $view);
});

it('keeps every value on a narrowed page and hides those without results', function (): void {
    $offered = count($this->listing()->valuesOf($this->first()));

    $document = $this->renderedUnder([$this->reserved(QueryParameter::Query) => self::NOTHING_MATCHES]);
    $values = $document->querySelectorAll($this->hooked(Hook::FacetValue));

    expect($offered)->toBeGreaterThan(0);
    expect($values)->toHaveCount($offered);
    expect(array_values(array_unique(array_map(static fn ($value): bool => $value->hasAttribute('hidden'), iterator_to_array($values)))))->toBe([true]);
    expect($document->querySelector($this->hooked(Hook::Facet))->hasAttribute('hidden'))->toBeTrue();
    expect($document->querySelector($this->hooked(Hook::More))->hasAttribute('hidden'))->toBeTrue();
});

it('shows a held value that has no result left', function (): void {
    $held = $this->listing()->valuesOf($this->first())[0]->slug;

    $document = $this->renderedUnder([
        $this->reserved(QueryParameter::Query) => self::NOTHING_MATCHES,
        $this->app->make(UrlParameters::class)->for($this->first()->taxonomy) => $held,
    ]);
    $input = $document->querySelector('input[value="'.$held.'"]');

    expect($input->hasAttribute('checked'))->toBeTrue();
    expect($input->closest($this->hooked(Hook::FacetValue))->hasAttribute('hidden'))->toBeFalse();
    expect($document->querySelector($this->hooked(Hook::Facet))->hasAttribute('hidden'))->toBeFalse();
});

it('marks pills with one attribute and nothing else', function (): void {
    $control = Blade::render($this->placing($this->first(), 'presentation="control"'));
    $this->app->forgetScopedInstances();
    $pill = Blade::render($this->placing($this->first(), 'presentation="pill"'));

    expect(explode('>', $pill)[0])->toContain(' data-presentation="pill"');
    $this->assertStringNotContainsString('data-presentation', $control);
    expect(str_replace(' data-presentation="pill"', '', $pill))->toBe($control);
});

it('presents a facet as its declaration says', function (): void {
    $declared = new Facet($this->first()->taxonomy, $this->first()->label, presentation: Presentation::Pill);

    $html = Blade::render('<x-meilifacets::listing.facet name="products" :facet="$declared" />', ['declared' => $declared]);

    expect($html)->toContain('data-presentation="pill"');
});

/** A plain attribute names only the module's presentations; a theme's arrives bound. */
it('marks a theme presentation a template binds with its name', function (): void {
    $html = Blade::render(
        '<x-meilifacets::listing.facet name="products" :facet="$name" :presentation="$presentation" />',
        ['name' => $this->first()->name, 'presentation' => FakePresentation::Tile],
    );

    expect(explode('>', $html)[0])->toContain('data-presentation="tile"');
});

/** R-10: the template cannot slip past the guard the declaration went through. */
it('refuses pills a template asks for on a single selection facet', function (): void {
    $single = new Facet($this->first()->taxonomy, $this->first()->label, SelectionMode::Single);

    $this->expectException(InvalidArgumentException::class);

    $this->app->make(FacetComponent::class, ['facet' => $single, 'presentation' => 'pill', 'name' => ProductListing::NAME]);
});

/** The description feeds the client, which counts and filters on facets the page never showed. */
it('still publishes a facet a template placed on its own', function (): void {
    $listing = $this->listing();
    $description = $this->app->make(ListingDescription::class);

    $before = $description->of($listing);
    $listing->placement()->placeNamed($this->first()->name, Facet::class);

    expect(json_encode($description->of($listing)))->toBe(json_encode($before));
    expect($description->of($listing)['facets'])->toHaveCount(count($listing->facets()));
});
