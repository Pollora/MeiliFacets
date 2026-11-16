<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Listing\FilterExpression;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\VisibleProducts;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Feature\Concerns\RequestsAnAddress;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Modules\MeiliFacets\View\ListingDescription;
use Tests\TestCase;

trait ListingDescriptionSteps
{
    private const string PROMOTIONS = 'on_sale';

    /**
     * @return list<string>
     */
    private function fieldsTheClientDeclares(): array
    {
        $source = (string) file_get_contents(__DIR__.'/../../resources/assets/ts/shared/description.ts');

        preg_match('/export interface ListingDescription \{(.*?)\n\}/s', $source, $block);
        preg_match_all('/^ {4}(\w+)\??:/m', $block[1] ?? '', $fields);

        return $fields[1];
    }

    private function describedWith(array $query): array
    {
        $this->app->forgetScopedInstances();
        request()->query->replace($query);
        request()->server->set('QUERY_STRING', http_build_query($query));

        return $this->app->make(ListingDescription::class)->of($this->listing());
    }

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->named(ProductListing::NAME);
    }
}

uses(TestCase::class, RequestsAnAddress::class, SwitchesTheSiteLocale::class, ListingDescriptionSteps::class);

// The suite shares one application: a query left behind would reach a later class.
afterEach(function (): void {
    request()->query->replace([]);
    request()->server->remove('QUERY_STRING');
    set_query_var(PageAddress::PAGED_QUERY_VAR, '');
    $this->app->forgetScopedInstances();
});

it('hands the client the state the server read', function (): void {
    $parameters = $this->app->make(UrlParameters::class);
    $taxonomy = $this->listing()->facets()[0]->taxonomy;

    set_query_var(PageAddress::PAGED_QUERY_VAR, '3');
    $state = $this->describedWith([
        $parameters->for($taxonomy) => 'globex,acme',
        $parameters->reserved(QueryParameter::MinPrice) => '12.5',
    ])['state'];

    expect((array) $state['facets'])->toBe([$taxonomy => ['acme', 'globex']]);
    expect($state['page'])->toBe(3);
    expect($state['price'])->toBe(['min' => 12.5, 'max' => null]);
    expect($state['query'])->toBe('');
    expect($state['sort'])->toBeNull();
});

it('reads the page from the path and not from a parameter', function (): void {
    $page = $this->app->make(UrlParameters::class)->reserved(QueryParameter::Page);

    expect($this->describedWith([$page => '3'])['state']['page'])->toBe(1);
});

// The engine answers slugs only: a pill the client draws takes its words from here (`R-57`).
it('hands the client the words of every value the page rendered', function (): void {
    $facets = $this->describedWith([])['facets'];
    $listing = $this->listing();

    foreach ($listing->facets() as $rank => $facet) {
        expect((array) $facets[$rank]['labels'])->toBe($listing->labelsOf($facet));
    }

    expect(array_filter(array_map(static fn (array $facet): array => (array) $facet['labels'], $facets)))->not->toBe([]);
});

it('hands the client the query that reads variants', function (): void {
    $variants = $this->describedWith([])['variantResults'];

    expect($variants['taxonomies'])->toBe($this->app->make(ProductListing::class)->variantTaxonomies());
    expect($variants['filter'])->toContain('NOT document_kind = "parent"');
    expect($variants['filter'])->toContain('post_type = "product_variation"');
    expect($variants['searchScope']['filter'])->toContain('NOT document_kind = "parent"');
    expect($variants['distinct'])->toBe('parent_id');
    expect($variants['attributes'])->toBe(['ID', 'card', 'parent_id']);
    expect($variants['sorts']['price_asc'][0] ?? null)->toBe('in_stock:desc');
});

// Missing, the whole site answers 500: the resolved listing copies the contract by hand.
it('always hands the client a base query', function (): void {
    expect($this->describedWith([])['baseQuery'])->toBe('');
});

// The panel counts what the page finds: the same products, on the same fields.
it('hands the client the scope the site search reads for products', function (): void {
    $product = $this->app->make(SearchableTypes::class)->all()[VisibleProducts::POST_TYPE];
    $scope = $this->describedWith([])['searchScope'];

    expect($scope['filter'])->toBe(FilterExpression::all($product->baseFilter));
    expect($scope['fields'])->toBe($product->searchOn);
    expect($scope['filter'])->toContain('exclude-from-search');
    expect($this->describedWith([])['filter'])->not->toContain('exclude-from-search');
});

// One threshold and one delay for every field that searches while typed.
it('hands the client the typing settings of the site search', function (): void {
    $this->app->instance(SearchSettings::class, new SearchSettings(3, 250));

    try {
        $description = $this->describedWith([]);
    } finally {
        $this->app->forgetInstance(SearchSettings::class);
    }

    expect([$description['minChars'], $description['delay']])->toBe([3, 250]);
});

it('hands over the path of the first page', function (): void {
    $this->requesting('/boutique/page/2?marque=globex');

    expect($this->describedWith([])['pagePath'])->toBe('/boutique');
});

it('hands over the counts of the values the page renders', function (): void {
    $description = $this->describedWith([]);
    $facet = $this->listing()->facets()[0];
    $expected = [];

    foreach ($this->listing()->valuesOf($facet) as $value) {
        $expected[$value->slug] = $value->count;
    }

    expect((array) $description['facets'][0]['counts'])->toBe($expected);
    expect($description['locale'])->toMatch('/^[a-z]{2,3}(-[A-Za-z0-9]+)*$/');
});

it('hands over its labels in the language wordpress translates the page in', function (): void {
    $description = $this->underLocales('fr', 'en_US', fn (): array => $this->describedWith([]));

    expect($description['locale'])->toBe('en-US');
    expect($description['countPattern'])->toBe(':count result|:count results');
    expect($description['sortPattern'])->toBe('Sort by: :choice');
});

it('hands over no selection as an empty object', function (): void {
    expect(json_encode($this->describedWith([])['state']['facets']))->toBe('{}');
});

it('hands over what wordpress read less the listings own parameters', function (): void {
    global $wp;

    $parameters = $this->app->make(UrlParameters::class);
    $owned = [
        $parameters->reserved(QueryParameter::MinPrice),
        $parameters->for($this->listing()->facets()[0]->taxonomy),
    ];

    $publicQueryVars = $wp->public_query_vars;
    array_walk($owned, $wp->add_query_var(...));

    try {
        $pageQuery = $this->describedWith([
            's' => '', 'post_type' => 'product', 'add-to-cart' => '12', 'paged' => '2',
            $owned[0] => '10', $owned[1] => 'acme',
        ])['pageQuery'];
    } finally {
        $wp->public_query_vars = $publicQueryVars;
    }

    expect($pageQuery)->toBe('s=&post_type=product');
});

it('tells the client which field promotions filter on', function (): void {
    if (! array_key_exists(self::PROMOTIONS, $this->listing()->sorts())) {
        $this->markTestSkipped('The host listing declares no price filter, so it offers no promotions.');
    }

    $description = $this->app->make(ListingDescription::class)->of($this->listing());

    expect(((array) $description['sortFilters'])[self::PROMOTIONS])->toBe(['field' => 'price.onsale', 'value' => 'true']);
    expect($description['sorts'][self::PROMOTIONS])->toBe([]);
});

// @param  array<string, string>  $query
// @return array<string, mixed>
// The same shape is written twice, once per language: a key added on one side only is a field nobody reads.
it('publishes exactly the fields the client declares', function (): void {
    $published = array_keys($this->describedWith([]));
    $declared = $this->fieldsTheClientDeclares();

    sort($published);
    sort($declared);

    expect($published)->toBe($declared);
});
