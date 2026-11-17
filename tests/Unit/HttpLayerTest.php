<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Http\Request;
use Modules\MeiliFacets\Contracts\EngineUnavailable;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Http\ServiceUnavailableHeaders;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\BuildsResolvedListings;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\UnavailableSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/** Reads WordPress and Laravel helpers, so each test gets a process of its own: see `Doubles\WordPress`. */
final class HttpLayerTest extends TestCase
{
    use BuildsResolvedListings;

    protected function setUp(): void
    {
        WordPress::boot();
        require_once __DIR__.'/Doubles/http-stubs.php';
    }

    #[Test]
    public function it_answers_a_503_that_no_cache_keeps_once_the_outage_is_announced(): void
    {
        $unavailable = new ServiceUnavailable;
        $response = new Response;

        $this->assertFalse($unavailable->wasAnnounced());
        $unavailable->announce();
        $unavailable->applyTo($response);

        $this->assertTrue($unavailable->wasAnnounced());
        $this->assertSame(503, $response->status);
        $this->assertSame(['Retry-After' => '120', 'Cache-Control' => 'no-store'], $response->headers->all);
    }

    #[Test]
    public function it_leaves_a_response_alone_when_no_outage_was_announced(): void
    {
        $unavailable = new ServiceUnavailable;
        $response = new Response;
        $middleware = new ServiceUnavailableHeaders($unavailable);

        $this->assertSame($response, $middleware->handle(new Request, static fn (): Response => $response));
        $this->assertSame(200, $response->status);

        $unavailable->announce();
        $middleware->handle(new Request, static fn (): Response => $response);

        $this->assertSame(503, $response->status);
    }

    #[Test]
    public function it_is_not_a_listing_page_until_a_listing_renders(): void
    {
        $page = new ListingPage;

        $this->assertFalse($page->isCurrent());
        $this->assertNull($page->pagination());

        $listing = $this->resolved();
        $page->markAsCurrent($listing);

        $this->assertTrue($page->isCurrent());
        $this->assertTrue($page->isPaginatedBy($listing));
        $this->assertFalse($page->isPaginatedBy($this->resolved()));
        $this->assertSame(1, $page->pagination()?->current);
    }

    #[Test]
    public function it_lets_a_filter_decide_whether_the_request_is_a_listing_page(): void
    {
        WordPress::fake('apply_filters', static fn (string $hook, bool $current): bool => $hook === ListingPage::FILTER ? true : $current);

        $this->assertTrue(new ListingPage()->isCurrent());
    }

    #[Test]
    public function it_has_no_pagination_when_the_engine_did_not_answer(): void
    {
        WordPress::fake('report', null);
        $page = new ListingPage;
        $page->markAsCurrent($this->resolved(new FakeListing, new ListingState, new UnavailableSearchEngine));

        $this->assertNull($page->pagination());
    }

    #[Test]
    public function it_keeps_the_first_listing_as_the_one_the_page_paginates(): void
    {
        $page = new ListingPage;
        $first = $this->resolved();
        $page->markAsCurrent($first);
        $page->markAsCurrent($this->resolved());

        $this->assertTrue($page->isPaginatedBy($first));
    }

    #[Test]
    public function it_marks_secondary_views_noindex_and_follow(): void
    {
        $policy = $this->policy(current: true);

        $this->assertSame(
            ['max-image-preview' => 'large', 'noindex' => true, 'follow' => true],
            $policy->asSecondaryView(['index' => true, 'max-image-preview' => 'large'])
        );
    }

    #[Test]
    public function it_leaves_robots_alone_on_a_bare_listing_path(): void
    {
        $robots = ['max-image-preview' => 'large'];

        $this->assertSame($robots, $this->policy(current: true)->noindexSecondaryViews($robots));
    }

    #[Test]
    public function it_deindexes_a_listing_carrying_a_filter(): void
    {
        WordPress::$query = ['f_product_brand' => 'acme'];

        $robots = $this->policy(current: true)->noindexSecondaryViews(['index' => true]);

        $this->assertSame(['noindex' => true, 'follow' => true], $robots);
    }

    #[Test]
    public function it_deindexes_a_paginated_listing_page(): void
    {
        WordPress::fake('is_paged', true);

        $this->assertTrue($this->policy(current: true)->isSecondaryView());
    }

    #[Test]
    public function it_never_deindexes_a_page_that_is_not_a_listing(): void
    {
        WordPress::$query = ['f_product_brand' => 'acme', 'q' => 'shoes'];
        WordPress::fake('is_paged', true);

        $this->assertFalse($this->policy(current: false)->isSecondaryView());
    }

    #[Test]
    public function it_counts_a_filled_listing_parameter_only(): void
    {
        $policy = $this->policy(current: true);

        $this->assertTrue($policy->appliesTo(['q' => 'shoes']));
        $this->assertFalse($policy->appliesTo(['q' => '   ']));
        $this->assertFalse($policy->appliesTo(['q' => ['a']]));
        $this->assertFalse($policy->appliesTo(['utm_source' => 'mail']));
        $this->assertFalse($policy->appliesTo([]));
    }

    #[Test]
    public function it_drops_the_links_yoast_chains_between_the_pages_of_a_listing(): void
    {
        $current = $this->policy(current: true);
        $other = $this->policy(current: false);

        $this->assertSame('', $current->dropLinkToTheNextPage('<link next>'));
        $this->assertSame('', $current->dropLinkToThePreviousPage('<link prev>'));
        $this->assertSame('<link next>', $other->dropLinkToTheNextPage('<link next>'));
        $this->assertSame('<link prev>', $other->dropLinkToThePreviousPage('<link prev>'));
    }

    #[Test]
    public function it_writes_the_path_of_each_page_of_the_listing(): void
    {
        WordPress::fake('get_pagenum_link', static fn (int $page): string => $page === PHP_INT_MAX
            ? 'https://example.test/shop/page/'.PHP_INT_MAX.'/?x=1'
            : 'https://example.test/shop/'.($page > 1 ? "page/{$page}/" : ''));

        $address = $this->address();

        $this->assertSame('/shop/', $address->path());
        $this->assertSame('/shop/page/3/', $address->pathOf(3));
        $this->assertSame('/shop/page/:page/', $address->pathPattern());
    }

    #[Test]
    public function it_falls_back_to_the_root_when_a_page_link_has_no_path(): void
    {
        WordPress::fake('get_pagenum_link', 'https://example.test');

        $this->assertSame('/', $this->address()->pathOf(1));
    }

    #[Test]
    public function it_carries_only_the_listing_and_wordpress_parameters_between_pages(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s', 'post_type', 'paged']];
        WordPress::$queryString = 'f_brand=acme&q=shoes&add-to-cart=12&paged=3&pg=2&s=ab&post_type[]=product&utm_source=x';

        $this->assertSame(
            '?f_brand=acme&q=shoes&s=ab&post_type[]=product',
            $this->address()->linkedQuery()
        );
    }

    #[Test]
    public function it_links_to_the_bare_path_when_no_parameter_travels(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => []];
        WordPress::$queryString = 'add-to-cart=12';

        $this->assertSame('', $this->address()->linkedQuery());
    }

    #[Test]
    public function it_keeps_the_wordpress_parameters_the_listing_does_not_handle_itself(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s', 'post_type', 'paged']];
        WordPress::$queryString = 's=ab&post_type=product&paged=2&q=x';

        $this->assertSame('post_type=product', $this->address()->queryWithout(['s']));
        $this->assertSame(['s=ab', 'post_type=product'], $this->address()->pairsWithout([]));
    }

    #[Test]
    public function it_reads_no_pair_from_an_empty_query_string(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s']];
        WordPress::$queryString = '';

        $this->assertSame('', $this->address()->queryWithout([]));
    }

    #[Test]
    public function it_reports_an_unavailable_engine_through_the_listing_page_without_raising(): void
    {
        $listing = $this->resolved(new FakeListing, new ListingState, new UnavailableSearchEngine);
        WordPress::fake('report', static function (EngineUnavailable $failure): void {
            WordPress::$config['reported'] = $failure::class;
        });

        $this->assertTrue($listing->failed());
        $this->assertSame(EngineUnavailable::class, WordPress::$config['reported']);
        $this->assertSame(0, $listing->total());
    }

    private function policy(bool $current): IndexingPolicy
    {
        $page = new ListingPage;

        if ($current) {
            $page->markAsCurrent($this->resolved());
        }

        return new IndexingPolicy(new UrlParameters(['product_brand' => 'f_brand']), $page);
    }

    private function address(): PageAddress
    {
        return new PageAddress(new ReservedParameters, new UrlParameters(['product_brand' => 'f_brand']));
    }
}
