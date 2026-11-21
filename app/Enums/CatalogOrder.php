<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/**
 * The default product sortings WooCommerce offers that the listing can follow
 * (`WC_Query::get_catalog_ordering_args()`).
 */
enum CatalogOrder: string
{
    case MenuOrder = 'menu_order';
    case Title = 'title';
    case Date = 'date';
    case Price = 'price';
    case Relevance = 'relevance';
    case Popularity = 'popularity';
    case Rating = 'rating';

    /**
     * `Popularity` and `Rating` have one direction only, as WooCommerce ranks them, whatever the setting says.
     *
     * @return list<string>
     */
    public function expressions(SortDirection $direction): array
    {
        return match ($this) {
            self::MenuOrder => [
                $direction->of(DocumentField::MenuOrder->value),
                $direction->of(DocumentField::Title->value),
            ],
            self::Title => [$direction->of(DocumentField::Title->value)],
            self::Date => [$direction->of(DocumentField::Date->value)],
            self::Price => [$direction->of($this->priceBound($direction)->path())],
            self::Relevance => [],
            self::Popularity => [
                SortDirection::Descending->of(DocumentField::TotalSales->value),
                SortDirection::Descending->of(DocumentField::Id->value),
            ],
            self::Rating => [
                SortDirection::Descending->of(DocumentField::AverageRating->value),
                SortDirection::Descending->of(DocumentField::RatingCount->value),
                SortDirection::Descending->of(DocumentField::Id->value),
            ],
        };
    }

    /** WooCommerce lists the newest, the most sold and the best rated first, and everything else from the start. */
    public function defaultDirection(): SortDirection
    {
        return match ($this) {
            self::Date, self::Popularity, self::Rating => SortDirection::Descending,
            default => SortDirection::Ascending,
        };
    }

    /** A product spanning 28–62 belongs at 28 going up and at 62 going down, as WooCommerce ranks it. */
    private function priceBound(SortDirection $direction): PriceField
    {
        return $direction === SortDirection::Descending ? PriceField::Max : PriceField::Min;
    }
}
