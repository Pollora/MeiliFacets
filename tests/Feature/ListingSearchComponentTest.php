<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Modules\MeiliFacets\Tests\Feature\Concerns\RequestsAnAddress;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Tests\TestCase;
use WooCommerce;
use WP_Query;

trait ListingSearchComponentHelpers
{
    /**
     * @return array{string, string} the parameter of a facet and the key of a sort the listing offers
     */
    private function aFacetAndASort(): array
    {
        $listing = $this->app->make(CurrentListing::class)->named(ProductListing::NAME);
        $facets = $listing->facets();
        $sort = array_key_first($listing->sorts());

        $this->assertNotEmpty($facets, 'The product listing declares its facets in code, whatever the catalogue.');
        $this->assertIsString($sort);

        return [$this->app->make(UrlParameters::class)->for($facets[0]->taxonomy), $sort];
    }

    private function document(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    private function field(string $html): Element
    {
        $field = $this->document($html)->querySelector($this->hooked(Hook::ListingSearchInput));
        $this->assertNotNull($field, 'The listing search renders no field.');

        return $field;
    }

    private function labelOf(string $html): Element
    {
        $label = $this->document($html)->querySelector('label[for="'.$this->field($html)->getAttribute('id').'"]');
        $this->assertNotNull($label, 'No label points at the field.');

        return $label;
    }

    private function hidden(string $name, string $escapedValue): string
    {
        return '<input type="hidden" name="'.$name.'" value="'.$escapedValue.'">';
    }

    private function reserved(QueryParameter $parameter): string
    {
        return $this->app->make(UrlParameters::class)->reserved($parameter);
    }

    /**
     * @param  array<string, string>  $vars
     * @param  Closure(): string  $render
     */
    private function onSearch(array $vars, Closure $render): string
    {
        global $wp_query;

        $search = new WP_Query;
        $search->parse_query($vars);
        $current = $wp_query;
        $wp_query = $search;
        $this->app->forgetScopedInstances();

        try {
            return $render();
        } finally {
            $wp_query = $current;
        }
    }

    /**
     * @param  array<string, string>  $query
     */
    private function ask(array $query): void
    {
        request()->query->replace($query);
        request()->server->set('QUERY_STRING', http_build_query($query));
        $this->app->forgetScopedInstances();
    }

    private function parameter(): string
    {
        return $this->app->make(UrlParameters::class)->reserved(QueryParameter::Query);
    }

    private function rendered(): string
    {
        return Blade::render('<x-meilifacets::listing.search name="products" />');
    }
}

/** The listing's search field, rendered by the server: a GET form that works before the client starts, and without it. */
uses(TestCase::class, FindsHooks::class, RequestsAnAddress::class, SwitchesTheSiteLocale::class, ListingSearchComponentHelpers::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('The only listing the module declares lists products.');
    }

    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->query->replace([]);
    request()->server->remove('QUERY_STRING');
    $this->app->forgetScopedInstances();
});

/** Without JavaScript, Enter sends the term under the name the listing reads, to the first page. */
it('sends the term to the first page without javascript', function (): void {
    $this->requesting('/boutique/page/2');

    $html = $this->rendered();

    expect($html)->toMatch('/<form class="meilifacetsListingSearch" role="search" method="get" action="\/boutique"/');
    $field = $this->field($html);
    expect($field->getAttribute('type'))->toBe('search');
    expect($field->getAttribute('name'))->toBe($this->parameter());
    expect($field->getAttribute('value'))->toBe('');
    expect($html)->toContain(Hook::ListingSearch->attribute()->toHtml());
    expect($html)->toContain(Hook::ListingSearchInput->attribute()->toHtml());
});

/** A plain permalink keeps its archive in the query: dropped, Enter would land on the home page. */
it('sends again the parameters wordpress reads', function (): void {
    $this->ask(['post_type' => 'product', $this->parameter() => 'old term', 'pg' => '3']);

    $html = $this->rendered();

    expect($html)->toContain('<input type="hidden" name="post_type" value="product">');
    expect(substr_count($html, 'type="hidden"'))->toBe(1);
});

/** Without JavaScript, Enter keeps what the visitor applied: only the term and the page are the form's own. */
it('sends again the facets the sort and the price applied', function (): void {
    [$facet, $sort] = $this->aFacetAndASort();
    $this->ask([
        $facet => 'globex,acme',
        $this->reserved(QueryParameter::Sort) => $sort,
        $this->reserved(QueryParameter::MinPrice) => '10.5',
        $this->reserved(QueryParameter::Query) => 'ser',
        $this->reserved(QueryParameter::Page) => '3',
    ]);

    $html = $this->rendered();

    expect($html)->toContain($this->hidden($facet, 'acme,globex'));
    expect($html)->toContain($this->hidden($this->reserved(QueryParameter::Sort), e($sort)));
    expect($html)->toContain($this->hidden($this->reserved(QueryParameter::MinPrice), '10.5'));
    $this->assertSame(3, substr_count($html, 'type="hidden"'), 'neither the term nor the page');
});

/** A value applied from the URL, and one WordPress reads, are both hostile until escaped. */
it('escapes what it sends again', function (): void {
    [$facet] = $this->aFacetAndASort();
    $hostile = '"><script>alert(1)</script>';
    $this->ask(['post_type' => $hostile, $facet => $hostile]);

    $html = $this->rendered();

    expect($html)->not->toContain('<script>');
    expect($html)->toContain($this->hidden('post_type', e($hostile)));
    expect($html)->toContain($this->hidden($facet, e($hostile)));
});

/** `/?s=creme&post_type=product&q=zzzz`: WordPress searches its own term, and no field could take `q` off. */
it('ignores its term on a search wordpress routed', function (): void {
    $this->ask([$this->parameter() => 'zzzz']);

    $typed = $this->onSearch(
        ['s' => 'creme', 'post_type' => 'product'],
        fn (): string => $this->app->make(CurrentListing::class)->named(ProductListing::NAME)->state()->query,
    );

    expect($typed)->toBe('');
});

/** `/boutique?q=%3F%28` serves the catalogue: the engine tokenises such a term into nothing (R-159). */
it('holds no term holding no letter and no figure', function (): void {
    $this->ask([$this->parameter() => '?(']);

    expect($this->field($this->rendered())->getAttribute('value'))->toBe('');
});

/** The name follows the project's own name for the parameter. */
it('names the field after the parameter the project chose', function (): void {
    $this->app->instance(UrlParameters::class, new UrlParameters([], [QueryParameter::Query->value => 'recherche']));

    try {
        $html = $this->rendered();
    } finally {
        $this->app->forgetInstance(UrlParameters::class);
    }

    expect($html)->toContain('name="recherche"');
});

it('holds the term the url carries and offers to clear it', function (): void {
    $this->ask([$this->parameter() => '"><b>ser']);

    $html = $this->rendered();

    expect($html)->toContain('value="&quot;&gt;&lt;b&gt;ser"');
    expect($html)->not->toContain('<b>');
    expect($html)->toMatch('/<button type="button" class="meilifacetsListingSearchClear" aria-label="[^"]+"\s+'.preg_quote(Hook::ListingSearchClear->attribute()->toHtml(), '/').'/');
});

it('hides the clear button while there is nothing to clear', function (): void {
    expect($this->rendered())->toMatch('/ hidden\s+'.preg_quote(Hook::ListingSearchClear->attribute()->toHtml(), '/').'/');
});

/** « Clear all » takes the term off too: a listing that only searches has something to clear. */
it('offers clear all for a term alone', function (): void {
    $this->ask([$this->parameter() => 'ser']);

    expect(Blade::render('<x-meilifacets::listing.reset name="products" />'))->not->toMatch('/ hidden /');
});

/** Named as a search landmark apart from the header's, in the language of the site; a theme may rename it. */
it('names the landmark and the field', function (): void {
    $html = $this->underLocales('en', 'en_US', $this->rendered(...));
    $form = $this->document($html)->querySelector($this->hooked(Hook::ListingSearch));

    expect($form?->getAttribute('role'))->toBe('search');
    expect($form?->getAttribute('aria-label'))->toBe('Search this list');
    expect($this->field($html)->getAttribute('placeholder'))->toBe('Search this list');
    expect($this->labelOf($html)->textContent)->toBe('Search this list');
    expect($this->labelOf(Blade::render('<x-meilifacets::listing.search name="products" label="Search the shop" />'))->textContent)->toBe('Search the shop');
});

/** A `<label>` names the field rather than an `aria-label`: hidden from sight, still read and still a click target. */
it('names the field by a visually hidden label', function (): void {
    $html = $this->rendered();

    expect($this->field($html)->hasAttribute('aria-label'))->toBeFalse();
    expect($this->labelOf($html)->getAttribute('class'))->toBe('meilifacetsHidden');
});

/** C-9: emptied on a search WordPress routed, the field would fall back on a term nothing can take off. */
it('renders nothing on a search wordpress routed', function (): void {
    expect(trim($this->onSearch(['s' => 'creme', 'post_type' => 'product'], $this->rendered(...))))->toBe('');
});

it('keeps the classes a theme adds', function (): void {
    expect(Blade::render('<x-meilifacets::listing.search name="products" class="grow" />'))->toContain('class="meilifacetsListingSearch grow"');
});
