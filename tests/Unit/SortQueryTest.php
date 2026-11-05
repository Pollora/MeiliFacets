<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Search\SortQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SortQueryTest extends TestCase
{
    private SortQuery $query;

    protected function setUp(): void
    {
        $this->query = new SortQuery([
            'on_sale' => SortFilter::whereTrue('price.onsale'),
            'in_stock' => SortFilter::whereTrue('in_stock'),
            'sale_only' => new SortFilter('price.onsale', 'yes'),
        ]);
    }

    #[Test]
    public function it_lists_each_filtered_field_once(): void
    {
        $this->assertSame(['price.onsale', 'in_stock'], $this->query->fields());
    }

    #[Test]
    public function it_filters_on_the_field_the_chosen_sort_carries(): void
    {
        $this->assertSame('in_stock = "true"', $this->query->clause(new ListingState(sort: 'in_stock')));
    }

    #[Test]
    public function it_adds_no_clause_when_no_sort_is_chosen(): void
    {
        $this->assertSame('', $this->query->clause(new ListingState));
    }

    #[Test]
    public function it_adds_no_clause_for_a_sort_that_filters_nothing(): void
    {
        $this->assertSame('', $this->query->clause(new ListingState(sort: 'price_asc')));
    }

    #[Test]
    public function it_counts_what_each_filtering_sort_would_keep(): void
    {
        $distribution = ['price.onsale' => ['true' => 4, 'false' => 9], 'in_stock' => ['true' => 11]];

        $this->assertSame(['on_sale' => 4, 'in_stock' => 11, 'sale_only' => 0], $this->query->matchesIn($distribution));
    }

    #[Test]
    public function it_has_nothing_to_filter_or_count_without_a_filtering_sort(): void
    {
        $query = new SortQuery([]);

        $this->assertSame([], $query->fields());
        $this->assertSame([], $query->matchesIn(['in_stock' => ['true' => 3]]));
        $this->assertSame('', $query->clause(new ListingState(sort: 'in_stock')));
    }
}
