<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\MeilisearchQuery;
use Modules\MeiliFacets\Search\PriceQuery;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchScopedListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MeilisearchQueryTest extends TestCase
{
    private const array SEARCHED_FIELDS = ['post_title', 'metas._sku'];

    #[Test]
    public function it_sends_every_search_that_carries_the_term_on_the_scope_fields(): void
    {
        $listing = $this->scopedListing('');
        $state = new ListingState(query: 'ser');
        $queries = [
            'results' => QueryPlan::results($listing, $state, []),
            'counts' => QueryPlan::measureWithout($listing, $state, new FacetQuery(new Facet('product_brand', 'Brand'))),
            'price bounds' => QueryPlan::measureWithout($listing, $state, new PriceQuery(new PriceFilter('Price'))),
        ];

        foreach ($queries as $name => $query) {
            $this->assertSame(self::SEARCHED_FIELDS, $this->translated($query)['attributesToSearchOn'] ?? null, $name);
        }
    }

    #[Test]
    public function it_sends_the_unfiltered_count_of_a_routed_search_on_the_scope_fields(): void
    {
        $query = QueryPlan::unfiltered($this->scopedListing('creme'));

        $this->assertSame(self::SEARCHED_FIELDS, $this->translated($query)['attributesToSearchOn'] ?? null);
    }

    #[Test]
    public function it_searches_every_field_when_nothing_is_searched(): void
    {
        $query = QueryPlan::results($this->scopedListing(''), new ListingState, []);

        $this->assertArrayNotHasKey('attributesToSearchOn', $this->translated($query));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function translated(array $query): array
    {
        return new MeilisearchQuery('posts')->from($query)->toArray();
    }

    private function scopedListing(string $baseQuery): FakeSearchScopedListing
    {
        $listing = new FakeListing(
            [new Facet('product_brand', 'Brand'), new PriceFilter('Price')],
            ['post_type = "product"'],
            baseQuery: $baseQuery,
        );

        return new FakeSearchScopedListing($listing, new SearchScope(['post_type = "product"'], self::SEARCHED_FIELDS));
    }
}
