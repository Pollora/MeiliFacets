<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Listing\VariationTaxonomies;
use Modules\MeiliFacets\Listing\VisibleProducts;
use Modules\MeiliFacets\Listing\WooCommerceDefaultSort;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Tests\Feature\Concerns\PinsIndexedPostTypes;
use Tests\TestCase;
use WooCommerce;
use WP_Query;

trait ProductSearchSteps
{
    private const string TERM = 'creme';

    private function scopeOnSearch(): SearchScope
    {
        return $this->withPinnedTypes(fn (): SearchScope => $this->onSearch(
            ['s' => self::TERM],
            static fn (ProductListing $listing): SearchScope => $listing->searchScope(),
        ));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function withPinnedTypes(Closure $read): mixed
    {
        $this->pinIndexedPostTypes();
        $this->app->forgetScopedInstances();

        try {
            return $read();
        } finally {
            $this->unpinIndexedPostTypes();
            $this->app->forgetScopedInstances();
        }
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function baseQueryOn(array $vars): string
    {
        return $this->onSearch($vars, static fn (ProductListing $listing): string => $listing->baseQuery());
    }

    /**
     * Swapping the query is what makes `is_search()` answer: it reads the global, never the URL.
     *
     * @template T
     *
     * @param  array<string, string>  $vars
     * @param  Closure(ProductListing): T  $read
     * @return T
     */
    private function onSearch(array $vars, Closure $read): mixed
    {
        global $wp_query;

        $search = new WP_Query;
        $search->parse_query($vars);
        $current = $wp_query;
        $wp_query = $search;

        try {
            return $read(new ProductListing(
                new WooCommerceFacets(new NameOrder),
                new WooCommerceSorts,
                $this->app->make(SearchableTypes::class),
                new VariationTaxonomies,
                new WooCommerceDefaultSort,
            ));
        } finally {
            $wp_query = $current;
        }
    }
}

uses(TestCase::class, PinsIndexedPostTypes::class, ProductSearchSteps::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('The product listing is WooCommerce\'s.');
    }
});

it('searches the term wordpress routed the page with', function (): void {
    expect($this->baseQueryOn(['s' => self::TERM]))->toBe(self::TERM);
});

it('searches nothing off a search', function (): void {
    expect($this->baseQueryOn([]))->toBe('');
});

// What the visitor pasted decides whether the page searches at all, so the edges are cut.
it('trims what the url carried', function (): void {
    expect($this->baseQueryOn(['s' => '  '.self::TERM.'  ']))->toBe(self::TERM);
});

// The term comes from the URL, so it is public input: the same bound the module puts on its own.
it('cuts a term at the length it allows its own', function (): void {
    $long = str_repeat('a', StateReader::MAX_QUERY_LENGTH + 50);

    expect(mb_strlen($this->baseQueryOn(['s' => $long])))->toBe(StateReader::MAX_QUERY_LENGTH);
});

// R-160: WooCommerce swaps the flag on a search, so a "search results only" product is found.
it('hides what woocommerce hides from its search and pins no term', function (): void {
    expect($this->scopeOnSearch()->filter)->toBe([
        'post_type = "product"',
        'post_status = "publish"',
        'NOT facets.product_visibility = "exclude-from-search"',
    ]);
});

// R-185 point 2: the flag follows the term, never the route — the base filter is the catalogue's everywhere.
it('browses the catalogue whatever the route', function (): void {
    $catalogue = [
        'post_type = "product"',
        'post_status = "publish"',
        'NOT facets.product_visibility = "exclude-from-catalog"',
    ];

    expect($this->onSearch(['s' => self::TERM], static fn (ProductListing $listing): array => $listing->baseFilter()))->toBe($catalogue);
    expect($this->onSearch([], static fn (ProductListing $listing): array => $listing->baseFilter()))->toBe($catalogue);
});

// The panel and the page count the same products on the same fields.
it('searches what the site search searches for products', function (): void {
    $scope = $this->scopeOnSearch();

    expect($scope->filter)->toBe([
        'post_type = "product"',
        'post_status = "publish"',
        'NOT facets.product_visibility = "exclude-from-search"',
    ]);
    expect($scope->fields)->toBe(['post_title', 'labels.product_brand', 'labels.product_cat', 'metas._sku']);
});

// A shop that leaves products out of the site search still hides from a search what WooCommerce hides.
it('searches every field when the site search leaves products out', function (): void {
    add_filter(self::INDEXED_POST_TYPES_OPTION, static fn (): array => ['post']);

    try {
        $this->app->forgetScopedInstances();
        $scope = $this->onSearch(['s' => self::TERM], fn (ProductListing $listing): SearchScope => $listing->searchScope());
    } finally {
        $this->unpinIndexedPostTypes();
        $this->app->forgetScopedInstances();
    }

    expect($scope->filter)->toBe([...VisibleProducts::inSearch()]);
    expect($scope->fields)->toBeNull();
});
