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

    /**
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
        };
    }

    /** WooCommerce lists the newest first unless asked otherwise, and everything else from the start. */
    public function defaultDirection(): SortDirection
    {
        return $this === self::Date ? SortDirection::Descending : SortDirection::Ascending;
    }

    /** A product spanning 28–62 belongs at 28 going up and at 62 going down, as WooCommerce ranks it. */
    private function priceBound(SortDirection $direction): PriceField
    {
        return $direction === SortDirection::Descending ? PriceField::Max : PriceField::Min;
    }
}
