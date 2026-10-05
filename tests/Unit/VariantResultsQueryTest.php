<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The results read the documents of the variants once a filter concerns a variant. */
final class VariantResultsQueryTest extends TestCase
{
    private const string PRODUCT_DOCUMENTS = 'NOT document_kind = "variant"';

    private const string VARIANT_DOCUMENTS = 'NOT document_kind = "parent"';

    private FakeVariantScopedListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listing = new FakeVariantScopedListing;
    }

    #[Test]
    public function it_reads_the_products_while_no_filter_concerns_a_variant(): void
    {
        $state = new ListingState(['product_brand' => ['acme']], 'price_asc');

        $query = QueryPlan::results($this->listing, $state, []);

        $this->assertFalse(QueryPlan::readsVariants($this->listing, $state));
        $this->assertStringContainsString(self::PRODUCT_DOCUMENTS, $query['filter']);
        $this->assertArrayNotHasKey('distinct', $query);
        $this->assertSame(['price.min:asc'], $query['sort']);
    }

    #[Test]
    public function it_reads_one_variant_per_product_once_a_size_is_ticked(): void
    {
        $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], 'price_asc');

        $query = QueryPlan::results($this->listing, $state, []);

        $this->assertTrue(QueryPlan::readsVariants($this->listing, $state));
        $this->assertStringContainsString(self::VARIANT_DOCUMENTS, $query['filter']);
        $this->assertStringNotContainsString(self::PRODUCT_DOCUMENTS, $query['filter']);
        $this->assertStringContainsString('facets.pa_size = "400ml"', $query['filter']);
        $this->assertSame('parent_id', $query['distinct']);
        $this->assertSame(['ID', 'card', 'parent_id'], $query['attributesToRetrieve']);
        $this->assertArrayNotHasKey('facets', $query);
    }

    #[Test]
    public function it_reads_variants_through_the_search_scope_once_a_term_is_searched(): void
    {
        $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], query: 'lotion');

        $filter = QueryPlan::results($this->listing, $state, [])['filter'];

        $this->assertStringContainsString('exclude-from-search', $filter);
        $this->assertStringContainsString(self::VARIANT_DOCUMENTS, $filter);
    }

    #[Test]
    public function it_sorts_variants_in_stock_first_under_a_price_sort_only(): void
    {
        $size = [FakeVariantScopedListing::SIZE => ['400ml']];

        $byPrice = QueryPlan::results($this->listing, new ListingState($size, 'price_asc'), []);
        $byDate = QueryPlan::results($this->listing, new ListingState($size, 'newest'), []);

        $this->assertSame(['in_stock:desc', 'price.min:asc'], $byPrice['sort']);
        $this->assertSame(['post_date:desc'], $byDate['sort']);
    }

    #[Test]
    public function it_reads_variants_under_a_price_range_alone(): void
    {
        $this->assertTrue(QueryPlan::readsVariants($this->listing, new ListingState(price: new Range(max: 30.0))));
    }

    #[Test]
    public function it_never_reads_variants_of_a_listing_without_a_document_per_variant(): void
    {
        $state = new ListingState(price: new Range(max: 30.0));

        $this->assertFalse(QueryPlan::readsVariants(FakeListing::withPriceAndBrand(), $state));
    }

    #[Test]
    public function it_measures_on_the_products_beside_the_variant_results(): void
    {
        $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], 'price_asc');

        $query = QueryPlan::measures($this->listing, $state, []);

        $this->assertStringContainsString(self::PRODUCT_DOCUMENTS, $query['filter']);
        $this->assertStringContainsString('facets.pa_size = "400ml"', $query['filter']);
        $this->assertSame(['facets.product_brand', 'facets.pa_size', 'price.min', 'price.max'], $query['facets']);
        $this->assertSame(0, $query['hitsPerPage']);
        $this->assertArrayNotHasKey('sort', $query);
    }
}
