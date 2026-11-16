<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Modules\MeiliFacets\Enums\ActiveValueKind;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Modules\MeiliFacets\View\ActiveValue;
use Tests\TestCase;

trait ActiveValuesComponentSteps
{
    /** Read from the catalogue, so a translation reworded there does not break the test. */
    private function removedRange(string $min, string $max): string
    {
        $range = __(':min – :max', ['min' => 'MINIMUM', 'max' => 'MAXIMUM']);
        $label = __('Remove the :label filter', ['label' => $range]);
        $pattern = str_replace(['MINIMUM', 'MAXIMUM'], [preg_quote($min, '/').'.*', preg_quote($max, '/').'.*'], preg_quote(e($label), '/'));

        return '/aria-label="'.$pattern.'/u';
    }

    private function host(string $html): Element
    {
        $host = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector($this->hooked(Hook::ActiveValues));
        $this->assertInstanceOf(Element::class, $host, 'The component renders no list.');

        return $host;
    }

    /**
     * The pills the server drew, the template's left out.
     *
     * @return list<Element>
     */
    private function pills(string $html): array
    {
        return iterator_to_array($this->host($html)->querySelectorAll('ul > li > '.$this->hooked(Hook::ActiveValue)), false);
    }

    private function labelOf(Element $pill): string
    {
        return trim((string) $pill->firstChild?->textContent);
    }

    /**
     * @return array{string, FacetValue}
     */
    private function aValue(): array
    {
        $listing = $this->app->make(CurrentListing::class)->named(ProductListing::NAME);

        foreach ($listing->facets() as $facet) {
            $values = $listing->valuesOf($facet);

            if ($values !== []) {
                $this->app->forgetScopedInstances();

                return [$facet->taxonomy, $values[0]];
            }
        }

        $this->markTestSkipped('The catalogue offers no facet value.');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function ask(array $query): void
    {
        request()->query->replace($query);
        $this->app->forgetScopedInstances();
    }

    private function parameters(): UrlParameters
    {
        return $this->app->make(UrlParameters::class);
    }

    private function renderComponent(): string
    {
        return Blade::render('<x-meilifacets::listing.active-values name="products" />');
    }
}

// One pill per filter the URL holds, drawn by the server so the page reads right before the client starts.
uses(TestCase::class, FindsHooks::class, SwitchesTheSiteLocale::class, ActiveValuesComponentSteps::class);

// The suite shares one application: a listing resolved here would reach a later class.
beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->query->replace([]);
    $this->app->forgetScopedInstances();
});

it('draws a pill per value the url holds', function (): void {
    [$taxonomy, $value] = $this->aValue();
    $parameter = $this->parameters()->for($taxonomy);
    $this->ask([$parameter => $value->slug]);

    $html = $this->underLocales('en', 'en_US', $this->renderComponent(...));
    [$pill] = $this->pills($html);

    expect($pill->getAttribute('name'))->toBe($parameter);
    expect($pill->getAttribute('value'))->toBe($value->slug);
    expect($pill->getAttribute('data-kind'))->toBe('term');
    expect($pill->getAttribute('aria-label'))->toBe('Remove the '.$value->label.' filter');
    expect($this->labelOf($pill))->toBe($value->label);
    expect($this->host($html)->hasAttribute('hidden'))->toBeFalse();
});

// Named by a visually hidden text read before the pills, which hides with the list when nothing is held.
it('names the list by a text read before its pills', function (): void {
    $host = $this->host($this->underLocales('en', 'en_US', $this->renderComponent(...)));
    $name = $host->firstElementChild;

    expect($host->hasAttribute('aria-label'))->toBeFalse();
    expect($name?->tagName)->toBe('SPAN');
    expect($name->getAttribute('class'))->toBe('meilifacetsHidden');
    expect($name->textContent)->toBe('Active filters');
    expect($name->nextElementSibling?->tagName)->toBe('UL');
    expect($name->nextElementSibling->getAttribute('role'))->toBe('list', 'styled without bullets, a list loses its role in Safari');
});

it('draws the price range as one pill in the language of the site', function (): void {
    $this->ask([
        $this->parameters()->reserved(QueryParameter::MinPrice) => '10',
        $this->parameters()->reserved(QueryParameter::MaxPrice) => '50',
    ]);

    [$html, $expected] = $this->underLocales('fr', 'fr_FR', fn (): array => [$this->renderComponent(), $this->removedRange('10,00', '50,00')]);

    expect($html)->toMatch($expected);
    expect($html)->toContain('10,00');
    $prices = array_filter($this->pills($html), fn (Element $pill): bool => $pill->getAttribute('data-kind') === 'price');
    expect($prices)->toHaveCount(1);
    expect(array_values($prices)[0]->getAttribute('name'))->toBe($this->parameters()->reserved(QueryParameter::MinPrice));
});

// The searched text is a filter like the others, named first. Placed against the
// price, which needs no catalogue: `ActiveValueListTest` places it against a ticked value.
it('draws the searched text first', function (): void {
    $query = $this->parameters()->reserved(QueryParameter::Query);
    $this->ask([$query => 'se"r', $this->parameters()->reserved(QueryParameter::MinPrice) => '10']);

    $pills = $this->pills($this->underLocales('en', 'en_US', $this->renderComponent(...)));

    expect(array_map(fn (Element $pill): string => $pill->getAttribute('data-kind'), $pills))->toBe(['search', 'price']);
    expect($pills[0]->getAttribute('name'))->toBe($query);
    expect($pills[0]->getAttribute('value'))->toBe('');
    expect($pills[0]->getAttribute('aria-label'))->toBe('Remove the “se"r” filter');
    expect($this->labelOf($pills[0]))->toBe('“se"r”');
});

it('ignores a value the page has no words for', function (): void {
    [$taxonomy] = $this->aValue();
    $this->ask([$this->parameters()->for($taxonomy) => '<script>alert(1)</script>']);

    $html = $this->renderComponent();

    expect($html)->not->toContain('alert(1)');
    expect(substr_count($html, Hook::ActiveValue->attribute()->toHtml()))->toBe(1, 'only the template holds a pill');
});

// Rendered empty too: the client draws into the list, it never creates one.
it('renders a hidden list and its template when nothing is held', function (): void {
    $html = $this->renderComponent();
    $opening = explode('>', $html)[0];

    expect($opening)->toContain(' hidden');
    expect($opening)->toContain(Hook::ActiveValues->attribute()->toHtml());
    expect($html)->toMatch('/<template '.preg_quote(Hook::ActiveValueTemplate->attribute()->toHtml(), '/').'>\s*<li[^>]*><button type="button" '.preg_quote(Hook::ActiveValue->attribute()->toHtml(), '/').'>/');
});

it('keeps the classes a theme adds', function (): void {
    expect(Blade::render('<x-meilifacets::listing.active-values name="products" class="flex" />'))->toContain('class="meilifacetsActiveValues flex"');
});

// A term name is typed by a shop manager, and a theme can override the catalogue.
it('escapes what it writes', function (): void {
    $hostile = '"><script>alert(1)</script>';

    $html = (string) view('meilifacets::components.listing.active-values', [
        'values' => [new ActiveValue($hostile, 'brand', $hostile, $hostile, ActiveValueKind::Term)],
        'attributes' => new ComponentAttributeBag,
        'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
    ])->render();

    expect($html)->not->toContain('<script>');
    expect(substr_count($html, '&quot;&gt;&lt;script&gt;'))->toBe(3);
});
