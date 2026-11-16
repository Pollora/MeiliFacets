<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\PageMetadata;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Support\NameRefused;
use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\BuildsResolvedListings;
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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Reads WordPress and Laravel helpers, so each test gets a process of its own: see `Doubles\WordPress`. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ListingRuntimeTest extends TestCase
{
    use BuildsResolvedListings;

    protected function setUp(): void
    {
        WordPress::boot();
    }

    #[Test]
    public function it_resolves_a_listing_once_per_name_from_the_request_query(): void
    {
        WordPress::$query = ['f_product_brand' => 'acme', 'q' => 'shoes', 'pg' => '9'];
        WordPress::fake('get_query_var', '3');
        $registry = new ListingRegistry(new Container);
        $registry->add(new FakeListing([new Facet('product_brand', 'Brand')], name: 'products'));
        $registry->add(FakeListing::named('articles'));
        $current = $this->current($registry);

        $products = $current->named('products');

        $this->assertSame($products, $current->named('products'));
        $this->assertSame(['acme'], $products->state()->selected('product_brand'));
        $this->assertSame('shoes', $products->state()->query);
        $this->assertSame(3, $products->state()->page);
        $this->assertNotSame($products, $current->named('articles'));
    }

    #[Test]
    public function it_resolves_the_only_listing_a_template_leaves_unnamed(): void
    {
        WordPress::fake('get_query_var', '');
        $registry = new ListingRegistry(new Container);
        $registry->add(FakeListing::named('products'));

        $this->assertSame('products', $this->current($registry)->onlyOne()->name());
    }

    #[Test]
    public function it_leaves_out_a_listing_that_cannot_be_built_without_taking_the_page_down(): void
    {
        $reported = [];
        WordPress::fake('report', static function (RuntimeException $failure) use (&$reported): void {
            $reported[] = $failure->getMessage();
        });
        $container = new Container;
        $container->bind(FakeListing::class, static fn () => throw new RuntimeException('project binding failed'));
        $registry = new ListingRegistry($container);
        $registry->addDeclaration(FakeListing::class);

        try {
            $registry->onlyOne();
            $this->fail('A listing that failed to build was served.');
        } catch (NameRefused) {
            $this->assertSame(['project binding failed'], $reported);
        }
    }

    #[Test]
    public function it_answers_what_a_view_asks_of_a_listing_from_the_listing_itself(): void
    {
        $listing = $this->resolved(new FakeVariantScopedListing, new ListingState(page: 2), new FakeSearchEngine(['results' => ['totalHits' => 50, 'hits' => []]]), reachableHits: 1000);

        $this->assertSame(16, $listing->perPage());
        $this->assertSame(16, $listing->offset());
        $this->assertSame(50, $listing->total());
        $this->assertSame('', $listing->baseQuery());
        $this->assertSame($listing->baseFilter(), array_values($listing->baseFilter()));
        $this->assertStringContainsString('post_type', $listing->baseFilterExpression());
        $this->assertSame('submit', $listing->applyMode()->value);
        $this->assertSame(['menu_order:asc', 'post_title:asc'], $listing->defaultSort());
        $this->assertSame([], $listing->sortMatches());
        $this->assertNull($listing->cardComponent());
        $this->assertFalse($listing->isRoutedSearch());
        $this->assertNotSame([], $listing->searchScope()->filter);
        $this->assertSame([], $this->resolved(new FakeListing)->defaultSort());
    }

    #[Test]
    public function it_reports_a_facet_whose_values_look_cut_by_the_engine(): void
    {
        $reported = [];
        WordPress::fake('report', static function (\Throwable $failure) use (&$reported): void {
            $reported[] = $failure->getMessage();
        });
        $brand = new Facet('product_brand', 'Brand');
        $values = array_fill_keys(array_map(static fn (int $n): string => "brand-{$n}", range(1, 100)), 1);
        $engine = new FakeSearchEngine(['results' => ['totalHits' => 1, 'hits' => [], 'facetDistribution' => ['facets.product_brand' => $values]]]);

        $this->resolved(new FakeListing([$brand]), new ListingState, $engine)->valuesOf($brand);

        $this->assertCount(1, $reported);
        $this->assertStringContainsString('returned 100 values', $reported[0]);
    }

    #[Test]
    public function it_describes_a_listing_for_the_browser_client(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s']];
        WordPress::$queryString = 's=shoes&f_product_brand=acme';
        WordPress::fake('has_action', false);
        WordPress::fake('get_pagenum_link', static fn (int $page): string => 'https://example.test/shop/'.($page === PHP_INT_MAX ? 'page/'.PHP_INT_MAX.'/' : ''));
        $listing = $this->resolved(
            new FakeVariantScopedListing,
            new ListingState(facets: ['product_brand' => ['acme']], query: 'shoes', sort: 'newest', page: 2),
            new FakeSearchEngine(['results' => ['totalHits' => 5, 'hits' => []]]),
        );

        $description = $this->description()->of($listing);

        $this->assertSame('fake', $description['name']);
        $this->assertSame(16, $description['perPage']);
        $this->assertSame('submit', $description['apply']);
        $this->assertSame(['product_brand', 'pa_size'], array_column($description['facets'], 'taxonomy'));
        $this->assertSame(['product_brand' => 'f_product_brand', 'pa_size' => 'f_pa_size'], $description['params']);
        $this->assertSame(['min' => 'price.min', 'max' => 'price.max'], $description['priceFields']);
        $this->assertSame(['price_asc', 'newest'], array_keys($description['sorts']));
        $this->assertSame(['menu_order:asc', 'post_title:asc'], $description['defaultSort']);
        $this->assertSame(['pa_size'], $description['variantResults']['taxonomies']);
        $this->assertSame('parent_id', $description['variantResults']['distinct']);
        $this->assertSame(['facets' => ['product_brand' => ['acme']], 'query' => 'shoes', 'sort' => 'newest', 'page' => 2, 'price' => ['min' => null, 'max' => null]], [
            'facets' => (array) $description['state']['facets'],
            'query' => $description['state']['query'],
            'sort' => $description['state']['sort'],
            'page' => $description['state']['page'],
            'price' => $description['state']['price'],
        ]);
        $this->assertSame('/shop/', $description['pagePath']);
        $this->assertSame('/shop/page/:page/', $description['pagePathPattern']);
        $this->assertSame('s=shoes', $description['pageQuery']);
        $this->assertNull($description['money']);
        $this->assertNull($description['pageTitles']);
        $this->assertSame(':count result|:count results', $description['countPattern']);
        $this->assertSame('en-US', $description['locale']);
    }

    #[Test]
    public function it_describes_the_page_metadata_only_for_the_listing_the_page_paginates(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => []];
        WordPress::fake('has_action', false);
        WordPress::fake('get_pagenum_link', 'https://example.test/shop/');
        $page = new ListingPage;
        $listing = $this->resolved(new FakeListing);
        $page->markAsCurrent($listing);

        $description = $this->description($page)->of($listing);

        $this->assertSame(['first' => 'Shop', 'paged' => 'Shop - Page :page'], $description['pageTitles']);
        $this->assertSame(['first' => 'https://example.test/shop/', 'paged' => 'https://example.test/shop/page/:page/'], $description['pageCanonicals']);
        $this->assertNull($description['pageRobots']);
        $this->assertNull($description['variantResults']);
        $this->assertNull($description['priceFields']);
        $this->assertNull($this->description($page)->of($this->resolved(new FakeListing))['pageTitles']);
    }

    private function current(ListingRegistry $registry): CurrentListing
    {
        $parameters = new UrlParameters;

        return new CurrentListing(
            $registry,
            new ListingSearch(new FakeSearchEngine, new DisjunctiveFacetCounter),
            new StateReader($parameters),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            $parameters,
            new EngineLimits(1000),
        );
    }

    private function description(?ListingPage $page = null): ListingDescription
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
}
