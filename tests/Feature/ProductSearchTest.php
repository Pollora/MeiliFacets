<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Search\VisibleProducts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;
use WP_Query;

final class ProductSearchTest extends TestCase
{
    use PinsIndexedPostTypes;

    private const string TERM = 'creme';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The product listing is WooCommerce\'s.');
        }
    }

    #[Test]
    public function it_searches_the_term_wordpress_routed_the_page_with(): void
    {
        $this->assertSame(self::TERM, $this->baseQueryOn(['s' => self::TERM]));
    }

    #[Test]
    public function it_searches_nothing_off_a_search(): void
    {
        $this->assertSame('', $this->baseQueryOn([]));
    }

    /** What the visitor pasted decides whether the page searches at all, so the edges are cut. */
    #[Test]
    public function it_trims_what_the_url_carried(): void
    {
        $this->assertSame(self::TERM, $this->baseQueryOn(['s' => '  '.self::TERM.'  ']));
    }

    /** The term comes from the URL, so it is public input: the same bound the module puts on its own. */
    #[Test]
    public function it_cuts_a_term_at_the_length_it_allows_its_own(): void
    {
        $long = str_repeat('a', StateReader::MAX_QUERY_LENGTH + 50);

        $this->assertSame(StateReader::MAX_QUERY_LENGTH, mb_strlen($this->baseQueryOn(['s' => $long])));
    }

    /** R-160: WooCommerce swaps the flag on a search, so a "search results only" product is found. */
    #[Test]
    public function it_hides_what_woocommerce_hides_from_its_search_and_pins_no_term(): void
    {
        $this->assertSame(
            ['post_type = "product"', 'post_status = "publish"', 'NOT facets.product_visibility = "exclude-from-search"'],
            $this->scopeOnSearch()->filter
        );
    }

    /** R-185 point 2: the flag follows the term, never the route — the base filter is the catalogue's everywhere. */
    #[Test]
    public function it_browses_the_catalogue_whatever_the_route(): void
    {
        $catalogue = ['post_type = "product"', 'post_status = "publish"', 'NOT facets.product_visibility = "exclude-from-catalog"'];

        $this->assertSame($catalogue, $this->onSearch(['s' => self::TERM], static fn (ProductListing $listing): array => $listing->baseFilter()));
        $this->assertSame($catalogue, $this->onSearch([], static fn (ProductListing $listing): array => $listing->baseFilter()));
    }

    /** The panel and the page count the same products on the same fields. */
    #[Test]
    public function it_searches_what_the_site_search_searches_for_products(): void
    {
        $scope = $this->scopeOnSearch();

        $this->assertSame(
            ['post_type = "product"', 'post_status = "publish"', 'NOT facets.product_visibility = "exclude-from-search"'],
            $scope->filter
        );
        $this->assertSame(['post_title', 'labels.product_brand', 'labels.product_cat', 'metas._sku'], $scope->fields);
    }

    /** A shop that leaves products out of the site search still hides from a search what WooCommerce hides. */
    #[Test]
    public function it_searches_every_field_when_the_site_search_leaves_products_out(): void
    {
        add_filter(self::INDEXED_POST_TYPES_OPTION, static fn (): array => ['post']);

        try {
            $this->app->forgetScopedInstances();
            $scope = $this->onSearch(['s' => self::TERM], fn (ProductListing $listing): SearchScope => $listing->searchScope());
        } finally {
            $this->unpinIndexedPostTypes();
            $this->app->forgetScopedInstances();
        }

        $this->assertSame([...VisibleProducts::inSearch()], $scope->filter);
        $this->assertNull($scope->fields);
    }

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
            ));
        } finally {
            $wp_query = $current;
        }
    }
}
