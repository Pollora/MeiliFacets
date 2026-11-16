<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\CatalogOrder;

use Modules\MeiliFacets\Enums\CatalogOrder;
use Modules\MeiliFacets\Enums\SortDirection;

it('sorts on the fields woocommerce sorts on', function (CatalogOrder $order, SortDirection $direction, array $expressions): void {
    expect($order->expressions($direction))->toBe($expressions);
})->with(function () {
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
});

it('lists the newest first and everything else from the start', function (): void {
    expect(CatalogOrder::Date->defaultDirection())->toBe(SortDirection::Descending);

    foreach ([CatalogOrder::MenuOrder, CatalogOrder::Title, CatalogOrder::Price, CatalogOrder::Relevance] as $order) {
        $this->assertSame(SortDirection::Ascending, $order->defaultDirection(), $order->name);
    }
});

/** `popularity` and `rating` read sales and reviews the index does not hold. */
it('does not know the sortings the listing cannot follow', function (): void {
    expect(CatalogOrder::tryFrom('popularity'))->toBeNull();
    expect(CatalogOrder::tryFrom('rating'))->toBeNull();
});
