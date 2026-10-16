<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Support\UrlParameters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;
use WP_Query;

/** The listing's search field, rendered by the server: a GET form that works before the client starts, and without it. */
final class ListingSearchComponentTest extends TestCase
{
    use FindsHooks;
    use RequestsAnAddress;
    use SwitchesTheSiteLocale;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The only listing the module declares lists products.');
        }

        $this->app->forgetScopedInstances();
    }

    protected function tearDown(): void
    {
        request()->query->replace([]);
        request()->server->remove('QUERY_STRING');
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    /** Without JavaScript, Enter sends the term under the name the listing reads, to the first page. */
    #[Test]
    public function it_sends_the_term_to_the_first_page_without_javascript(): void
    {
        $this->requesting('/boutique/page/2');

        $html = $this->rendered();

        $this->assertMatchesRegularExpression('/<form class="meilifacetsListingSearch" role="search" method="get" action="\/boutique"/', $html);
        $field = $this->field($html);
        $this->assertSame('search', $field->getAttribute('type'));
        $this->assertSame($this->parameter(), $field->getAttribute('name'));
        $this->assertSame('', $field->getAttribute('value'));
        $this->assertStringContainsString(Hook::ListingSearch->attribute()->toHtml(), $html);
        $this->assertStringContainsString(Hook::ListingSearchInput->attribute()->toHtml(), $html);
    }

    /** A plain permalink keeps its archive in the query: dropped, Enter would land on the home page. */
    #[Test]
    public function it_sends_again_the_parameters_wordpress_reads(): void
    {
        $this->ask(['post_type' => 'product', $this->parameter() => 'old term', 'pg' => '3']);

        $html = $this->rendered();

        $this->assertStringContainsString('<input type="hidden" name="post_type" value="product">', $html);
        $this->assertSame(1, substr_count($html, 'type="hidden"'));
    }

    /** Without JavaScript, Enter keeps what the visitor applied: only the term and the page are the form's own. */
    #[Test]
    public function it_sends_again_the_facets_the_sort_and_the_price_applied(): void
    {
        [$facet, $sort] = $this->aFacetAndASort();
        $this->ask([
            $facet => 'globex,acme',
            $this->reserved(QueryParameter::Sort) => $sort,
            $this->reserved(QueryParameter::MinPrice) => '10.5',
            $this->reserved(QueryParameter::Query) => 'ser',
            $this->reserved(QueryParameter::Page) => '3',
        ]);

        $html = $this->rendered();

        $this->assertStringContainsString($this->hidden($facet, 'acme,globex'), $html);
        $this->assertStringContainsString($this->hidden($this->reserved(QueryParameter::Sort), e($sort)), $html);
        $this->assertStringContainsString($this->hidden($this->reserved(QueryParameter::MinPrice), '10.5'), $html);
        $this->assertSame(3, substr_count($html, 'type="hidden"'), 'neither the term nor the page');
    }

    /** A value applied from the URL, and one WordPress reads, are both hostile until escaped. */
    #[Test]
    public function it_escapes_what_it_sends_again(): void
    {
        [$facet] = $this->aFacetAndASort();
        $hostile = '"><script>alert(1)</script>';
        $this->ask(['post_type' => $hostile, $facet => $hostile]);

        $html = $this->rendered();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString($this->hidden('post_type', e($hostile)), $html);
        $this->assertStringContainsString($this->hidden($facet, e($hostile)), $html);
    }

    /** `/?s=creme&post_type=product&q=zzzz`: WordPress searches its own term, and no field could take `q` off. */
    #[Test]
    public function it_ignores_its_term_on_a_search_wordpress_routed(): void
    {
        $this->ask([$this->parameter() => 'zzzz']);

        $typed = $this->onSearch(
            ['s' => 'creme', 'post_type' => 'product'],
            fn (): string => $this->app->make(CurrentListing::class)->named(ProductListing::NAME)->typedTerm(),
        );

        $this->assertSame('', $typed);
    }

    /** `/boutique?q=%3F%28` serves the catalogue: the engine tokenises such a term into nothing (R-159). */
    #[Test]
    public function it_holds_no_term_holding_no_letter_and_no_figure(): void
    {
        $this->ask([$this->parameter() => '?(']);

        $this->assertSame('', $this->field($this->rendered())->getAttribute('value'));
    }

    /** The name follows the project's own name for the parameter. */
    #[Test]
    public function it_names_the_field_after_the_parameter_the_project_chose(): void
    {
        $this->app->instance(UrlParameters::class, new UrlParameters([], [QueryParameter::Query->value => 'recherche']));

        try {
            $html = $this->rendered();
        } finally {
            $this->app->forgetInstance(UrlParameters::class);
        }

        $this->assertStringContainsString('name="recherche"', $html);
    }

    #[Test]
    public function it_holds_the_term_the_url_carries_and_offers_to_clear_it(): void
    {
        $this->ask([$this->parameter() => '"><b>ser']);

        $html = $this->rendered();

        $this->assertStringContainsString('value="&quot;&gt;&lt;b&gt;ser"', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertMatchesRegularExpression('/<button type="button" class="meilifacetsListingSearchClear" aria-label="[^"]+"\s+'.preg_quote(Hook::ListingSearchClear->attribute()->toHtml(), '/').'/', $html);
    }

    #[Test]
    public function it_hides_the_clear_button_while_there_is_nothing_to_clear(): void
    {
        $this->assertMatchesRegularExpression('/ hidden\s+'.preg_quote(Hook::ListingSearchClear->attribute()->toHtml(), '/').'/', $this->rendered());
    }

    /** « Clear all » takes the term off too: a listing that only searches has something to clear. */
    #[Test]
    public function it_offers_clear_all_for_a_term_alone(): void
    {
        $this->ask([$this->parameter() => 'ser']);

        $this->assertDoesNotMatchRegularExpression('/ hidden /', Blade::render('<x-meilifacets::listing.reset name="products" />'));
    }

    /** Named as a search landmark apart from the header's, in the language of the site; a theme may rename it. */
    #[Test]
    public function it_names_the_landmark_and_the_field(): void
    {
        $html = $this->underLocales('en', 'en_US', $this->rendered(...));
        $form = $this->document($html)->querySelector($this->hooked(Hook::ListingSearch));

        $this->assertSame('search', $form?->getAttribute('role'));
        $this->assertSame('Search this list', $form?->getAttribute('aria-label'));
        $this->assertSame('Search this list', $this->field($html)->getAttribute('placeholder'));
        $this->assertSame('Search this list', $this->labelOf($html)->textContent);
        $this->assertSame('Search the shop', $this->labelOf(Blade::render('<x-meilifacets::listing.search name="products" label="Search the shop" />'))->textContent);
    }

    /** A `<label>` names the field rather than an `aria-label`: hidden from sight, still read and still a click target. */
    #[Test]
    public function it_names_the_field_by_a_visually_hidden_label(): void
    {
        $html = $this->rendered();

        $this->assertFalse($this->field($html)->hasAttribute('aria-label'));
        $this->assertSame('meilifacetsHidden', $this->labelOf($html)->getAttribute('class'));
    }

    /** C-9: emptied on a search WordPress routed, the field would fall back on a term nothing can take off. */
    #[Test]
    public function it_renders_nothing_on_a_search_wordpress_routed(): void
    {
        $this->assertSame('', trim($this->onSearch(['s' => 'creme', 'post_type' => 'product'], $this->rendered(...))));
    }

    #[Test]
    public function it_keeps_the_classes_a_theme_adds(): void
    {
        $this->assertStringContainsString('class="meilifacetsListingSearch grow"', Blade::render('<x-meilifacets::listing.search name="products" class="grow" />'));
    }

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
