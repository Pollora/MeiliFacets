<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ListingDescriptionShape;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\PageMetadata;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\ActiveValueList;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\ListingDescription;
use Modules\MeiliFacets\View\ListingRobots;

function describing(?ListingPage $page = null): ListingDescription
{
    $parameters = new UrlParameters;
    $page ??= new ListingPage;
    $metadata = new class implements PageMetadata
    {
        public function titles(): array
        {
            return ['first' => 'Shop', 'paged' => 'Shop - Page :page'];
        }

        public function canonicals(): array
        {
            return ['first' => 'https://example.test/shop/', 'paged' => 'https://example.test/shop/page/:page/'];
        }
    };

    return new ListingDescription(
        $parameters,
        new EngineLimits(1000),
        new Money,
        new PageAddress(new ReservedParameters, $parameters),
        new CountLabel(static fn (): string => 'en_US'),
        new ActiveValueList(new Money, $parameters),
        new SearchSettings,
        $metadata,
        $page,
        new ListingRobots(new IndexingPolicy($parameters, $page)),
    );
}

beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    $GLOBALS['wp'] = (object) ['public_query_vars' => ['s']];
    WordPress::$queryString = 's=shoes&f_product_brand=acme&orderby=date';
    WordPress::fake('has_action', false);
    WordPress::fake('get_pagenum_link', static fn (int $page): string => 'https://example.test/shop/'.($page === PHP_INT_MAX ? 'page/'.PHP_INT_MAX.'/' : ''));
    WordPress::fake('wp_strip_all_tags', static fn (string $text): string => strip_tags($text));
    WordPress::fake('get_woocommerce_price_format', '%2$s&nbsp;%1$s');
    WordPress::fake('get_woocommerce_currency_symbol', '&euro;');
    WordPress::fake('wc_get_price_decimals', 2);
    WordPress::fake('wc_get_price_decimal_separator', ',');
    WordPress::fake('wc_get_price_thousand_separator', '.');
});

afterEach(function (): void {
    unset($GLOBALS['wp']);
});

function listed(Listing $listing, ListingState $state): ResolvedListing
{
    $answer = ['totalHits' => 5, 'hits' => [], 'facetDistribution' => [
        'facets.product_brand' => ['acme' => 3, 'globex' => 2],
        'facets.pa_size' => ['large' => 4],
    ]];

    return new ResolvedListing(
        $listing,
        $state,
        new ListingSearch(new FakeSearchEngine([
            'results' => $answer,
            'count:product_brand' => $answer,
            'count:pa_size' => $answer,
            'unfiltered' => $answer,
        ]), new DisjunctiveFacetCounter),
        new FacetValues(new FakeTermLabels(['acme' => 'Acme', 'globex' => 'Globex', 'large' => 'Large']), new FakeTermScope, new FakeDefaultTerms),
        new EngineLimits(1000),
    );
}

/** The unfiltered catalogue every scenario below starts from: the browser client reads each key of it. */
function patterns(): array
{
    return [
        'countPattern' => ':count result|:count results',
        'filterPattern' => ':count active filter|:count active filters',
        'totalPattern' => ':count item|:count items',
        'sortPattern' => 'Sort by: :choice',
        'activeValuePatterns' => [
            'remove' => 'Remove the :label filter',
            'query' => '“:query”',
            'between' => ':min – :max',
            'from' => 'From :min',
            'upTo' => 'Up to :max',
        ],
    ];
}

it('describes a variant listing key by key for the browser client', function (): void {
    $listing = listed(new FakeVariantScopedListing, new ListingState(facets: ['product_brand' => ['acme']], query: 'shoes', sort: 'newest', page: 2, price: new Range(10.0, 50.0)));
    $page = new ListingPage;
    $page->markAsCurrent($listing);

    $description = describing($page)->of($listing);

    $visible = 'post_type = "product" AND post_status = "publish" AND NOT facets.product_visibility = "exclude-from-catalog"';
    $searchable = 'post_type = "product" AND post_status = "publish" AND NOT facets.product_visibility = "exclude-from-search"';
    $brand = ['taxonomy' => 'product_brand', 'multiple' => true, 'cap' => 30, 'visible' => 10, 'counts' => (object) ['acme' => 3, 'globex' => 2], 'labels' => (object) ['acme' => 'Acme', 'globex' => 'Globex']];
    $size = ['taxonomy' => 'pa_size', 'multiple' => true, 'cap' => 30, 'visible' => 10, 'counts' => (object) ['large' => 4], 'labels' => (object) ['large' => 'Large']];

    expect($description)->toEqual([
        'name' => 'fake',
        'filter' => $visible,
        'baseQuery' => '',
        'searchScope' => ['filter' => $searchable, 'fields' => null],
        'minChars' => 2,
        'delay' => 120,
        'perPage' => 16,
        'reachableHits' => 1000,
        'attributes' => ['ID', 'card'],
        'variantResults' => [
            'taxonomies' => ['pa_size'],
            'filter' => '(post_type = "product" OR post_type = "product_variation") AND post_status = "publish" AND NOT facets.product_visibility = "exclude-from-catalog" AND NOT document_kind = "parent"',
            'searchScope' => [
                'filter' => '(post_type = "product" OR post_type = "product_variation") AND post_status = "publish" AND NOT facets.product_visibility = "exclude-from-search" AND NOT document_kind = "parent"',
                'fields' => null,
            ],
            'distinct' => 'parent_id',
            'attributes' => ['ID', 'card', 'parent_id'],
            'sorts' => ['price_asc' => ['in_stock:desc', 'price.min:asc'], 'newest' => ['post_date:desc']],
            'defaultSort' => ['menu_order:asc', 'post_title:asc'],
        ],
        'apply' => 'submit',
        'facets' => [$brand, $size],
        'params' => ['product_brand' => 'f_product_brand', 'pa_size' => 'f_pa_size'],
        'reserved' => ['sort' => 'sort', 'query' => 'q', 'page' => 'pg', 'minPrice' => 'min_price', 'maxPrice' => 'max_price'],
        'priceFields' => ['min' => 'price.min', 'max' => 'price.max'],
        'money' => ['format' => "%2\$s\u{A0}%1\$s", 'symbol' => '€', 'decimals' => 2, 'decimal' => ',', 'thousand' => '.'],
        'sorts' => ['price_asc' => ['price.min:asc'], 'newest' => ['post_date:desc']],
        'defaultSort' => ['menu_order:asc', 'post_title:asc'],
        'sortFilters' => (object) [],
        ...patterns(),
        'locale' => 'en-US',
        'state' => [
            'facets' => (object) ['product_brand' => ['acme']],
            'query' => 'shoes',
            'sort' => 'newest',
            'page' => 2,
            'price' => ['min' => 10.0, 'max' => 50.0],
        ],
        'pagePath' => '/shop/',
        'pagePathPattern' => '/shop/page/:page/',
        'pageQuery' => 's=shoes',
        'pageTitles' => ['first' => 'Shop', 'paged' => 'Shop - Page :page'],
        'pageCanonicals' => ['first' => 'https://example.test/shop/', 'paged' => 'https://example.test/shop/page/:page/'],
        'pageRobots' => null,
    ]);
    expect($description['state']['facets'])->toBeObject();
    expect($description['sortFilters'])->toBeObject();
    expect($description['facets'][0]['counts'])->toBeObject();
    expect($description['facets'][0]['labels'])->toBeObject();
});

it('describes the sort filters a listing carries and no page metadata for a listing the page does not paginate', function (): void {
    $listing = listed(FakeListing::withPromotions(), new ListingState);

    $description = describing()->of($listing);

    expect($description['sortFilters'])->toEqual((object) ['on_sale' => ['field' => 'price.onsale', 'value' => 'true']]);
    expect($description['sorts'])->toBe(['price_asc' => ['metas._price:asc'], 'on_sale' => []]);
    expect($description['variantResults'])->toBeNull();
    expect($description['pageTitles'])->toBeNull();
    expect($description['pageCanonicals'])->toBeNull();
    expect($description['pageRobots'])->toBeNull();
    expect($description['state']['facets'])->toEqual((object) []);
    expect($description['state']['sort'])->toBeNull();
    expect($description['state']['page'])->toBe(1);
    expect($description['state']['price'])->toBe(['min' => null, 'max' => null]);
});

it('names no price field for a listing that declares no price range', function (): void {
    $listing = listed(new FakeListing([new Facet('product_brand', 'Brand')]), new ListingState);

    expect(describing()->of($listing)['priceFields'])->toBeNull();
});
