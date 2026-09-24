<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;
use WP_Query;

final class ProductSearchTest extends TestCase
{
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

    #[Test]
    public function it_pins_no_term_of_the_catalogue_on_a_search(): void
    {
        $this->assertSame(
            ['post_type = "product"', 'post_status = "publish"', 'NOT facets.product_visibility = "exclude-from-catalog"'],
            $this->onSearch(['s' => self::TERM], static fn (ProductListing $listing): array => $listing->baseFilter())
        );
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
            return $read(new ProductListing(new WooCommerceFacets(new NameOrder), new WooCommerceSorts));
        } finally {
            $wp_query = $current;
        }
    }
}
