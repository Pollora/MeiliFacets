<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\CatalogOrder;
use Modules\MeiliFacets\Enums\SortDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{CatalogOrder, SortDirection, list<string>}>
     */
    public static function orders(): iterable
    {
        yield 'custom ordering, ties broken by name' => [CatalogOrder::MenuOrder, SortDirection::Ascending, ['menu_order:asc', 'post_title:asc']];
        yield 'custom ordering reversed, tie-break reversed too' => [CatalogOrder::MenuOrder, SortDirection::Descending, ['menu_order:desc', 'post_title:desc']];
        yield 'name' => [CatalogOrder::Title, SortDirection::Ascending, ['post_title:asc']];
        yield 'name reversed' => [CatalogOrder::Title, SortDirection::Descending, ['post_title:desc']];
        yield 'date' => [CatalogOrder::Date, SortDirection::Descending, ['post_date:desc']];
        yield 'date oldest first' => [CatalogOrder::Date, SortDirection::Ascending, ['post_date:asc']];
        yield 'price going up, on the lowest price' => [CatalogOrder::Price, SortDirection::Ascending, ['price.min:asc']];
        yield 'price going down, on the highest price' => [CatalogOrder::Price, SortDirection::Descending, ['price.max:desc']];
        yield 'relevance' => [CatalogOrder::Relevance, SortDirection::Ascending, []];
        yield 'relevance has no reverse' => [CatalogOrder::Relevance, SortDirection::Descending, []];
    }

    /**
     * @param  list<string>  $expressions
     */
    #[DataProvider('orders')]
    #[Test]
    public function it_sorts_on_the_fields_woocommerce_sorts_on(CatalogOrder $order, SortDirection $direction, array $expressions): void
    {
        $this->assertSame($expressions, $order->expressions($direction));
    }

    #[Test]
    public function it_lists_the_newest_first_and_everything_else_from_the_start(): void
    {
        $this->assertSame(SortDirection::Descending, CatalogOrder::Date->defaultDirection());

        foreach ([CatalogOrder::MenuOrder, CatalogOrder::Title, CatalogOrder::Price, CatalogOrder::Relevance] as $order) {
            $this->assertSame(SortDirection::Ascending, $order->defaultDirection(), $order->name);
        }
    }

    /** `popularity` and `rating` read sales and reviews the index does not hold. */
    #[Test]
    public function it_does_not_know_the_sortings_the_listing_cannot_follow(): void
    {
        $this->assertNull(CatalogOrder::tryFrom('popularity'));
        $this->assertNull(CatalogOrder::tryFrom('rating'));
    }
}
