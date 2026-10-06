<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\FilterQuery;
use Modules\MeiliFacets\Search\PriceQuery;
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
    public function it_reads_products_under_a_price_range_alone_as_woocommerce_does(): void
    {
        $state = new ListingState(sort: 'price_asc', price: new Range(min: 30.0, max: 35.0));

        $query = QueryPlan::results($this->listing, $state, []);

        $this->assertFalse(QueryPlan::readsVariants($this->listing, $state));
        $this->assertStringContainsString(self::PRODUCT_DOCUMENTS, $query['filter']);
        $this->assertStringContainsString('price.min <= 35 AND price.max >= 30', $query['filter']);
        $this->assertArrayNotHasKey('distinct', $query);
        $this->assertSame(['price.min:asc'], $query['sort']);
    }

    #[Test]
    public function it_never_reads_variants_of_a_listing_without_a_document_per_variant(): void
    {
        $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']]);

        $this->assertFalse(QueryPlan::readsVariants(FakeListing::withPriceAndBrand(), $state));
    }

    #[Test]
    public function it_measures_once_per_product_on_the_variants_beside_the_variant_results(): void
    {
        $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], 'price_asc');

        $query = QueryPlan::measures($this->listing, $state, ['count:pa_size']);

        $this->assertStringContainsString(self::VARIANT_DOCUMENTS, $query['filter']);
        $this->assertStringContainsString('facets.pa_size = "400ml"', $query['filter']);
        $this->assertSame('parent_id', $query['distinct']);
        $this->assertSame(['facets.product_brand', 'price.min', 'price.max'], $query['facets']);
        $this->assertSame(0, $query['hitsPerPage']);
        $this->assertSame(1, $query['page']);
        $this->assertArrayNotHasKey('sort', $query);
    }

    #[Test]
    public function it_counts_the_sizes_once_per_variant_under_the_other_filters(): void
    {
        $state = new ListingState(['product_brand' => ['acme']], price: new Range(min: 30.0, max: 35.0));

        $query = QueryPlan::measureWithout($this->listing, $state, $this->sizeQuery());

        $this->assertStringContainsString(self::VARIANT_DOCUMENTS, $query['filter']);
        $this->assertStringContainsString('facets.product_brand = "acme"', $query['filter']);
        $this->assertStringContainsString('price.min <= 35 AND price.max >= 30', $query['filter']);
        $this->assertArrayNotHasKey('distinct', $query);
        $this->assertSame(['facets.pa_size'], $query['facets']);
    }

    #[Test]
    public function it_counts_a_ticked_size_without_its_own_clause(): void
    {
        $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']]);

        $query = QueryPlan::measureWithout($this->listing, $state, $this->sizeQuery());

        $this->assertStringNotContainsString('facets.pa_size', $query['filter']);
        $this->assertArrayNotHasKey('distinct', $query);
    }

    #[Test]
    public function it_counts_a_shared_facet_once_per_product_while_a_size_is_ticked(): void
    {
        $state = new ListingState(['product_brand' => ['acme'], FakeVariantScopedListing::SIZE => ['400ml']]);

        $query = QueryPlan::measureWithout($this->listing, $state, $this->brandQuery());

        $this->assertStringContainsString(self::VARIANT_DOCUMENTS, $query['filter']);
        $this->assertStringContainsString('facets.pa_size = "400ml"', $query['filter']);
        $this->assertSame('parent_id', $query['distinct']);
    }

    #[Test]
    public function it_counts_a_shared_facet_on_the_products_while_no_size_is_ticked(): void
    {
        $state = new ListingState(['product_brand' => ['acme']], price: new Range(max: 30.0));

        $query = QueryPlan::measureWithout($this->listing, $state, $this->brandQuery());

        $this->assertStringContainsString(self::PRODUCT_DOCUMENTS, $query['filter']);
        $this->assertArrayNotHasKey('distinct', $query);
    }

    #[Test]
    public function it_bounds_the_price_on_every_variant_while_a_size_is_ticked(): void
    {
        $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], price: new Range(max: 30.0));

        $query = QueryPlan::measureWithout($this->listing, $state, $this->priceQuery());

        $this->assertStringContainsString(self::VARIANT_DOCUMENTS, $query['filter']);
        $this->assertStringNotContainsString('price.min <=', $query['filter']);
        $this->assertArrayNotHasKey('distinct', $query);
    }

    #[Test]
    public function it_offers_the_facets_of_the_variant_taxonomies_only(): void
    {
        $keys = array_map(static fn (FacetQuery $query): string => $query->key(), QueryPlan::variantFacetQueries($this->listing));

        $this->assertSame(['count:pa_size'], $keys);
        $this->assertSame([], QueryPlan::variantFacetQueries(FakeListing::withPriceAndBrand()));
    }

    private function sizeQuery(): FilterQuery
    {
        return $this->queryKeyed('count:pa_size');
    }

    private function brandQuery(): FilterQuery
    {
        return $this->queryKeyed('count:product_brand');
    }

    private function priceQuery(): FilterQuery
    {
        return $this->queryKeyed(PriceQuery::KEY);
    }

    private function queryKeyed(string $key): FilterQuery
    {
        $queries = array_filter(QueryPlan::filterQueries($this->listing), static fn (FilterQuery $query): bool => $query->key() === $key);

        return array_values($queries)[0];
    }
}
