<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\CatalogOrder;
use Modules\MeiliFacets\Enums\SortDirection;

/** A WooCommerce catalog ordering setting, read as `WC_Query::get_catalog_ordering_args()` reads it. */
final readonly class CatalogOrderSetting
{
    private const string DIRECTION_SEPARATOR = '-';

    private const string NO_DIRECTION = '';

    private function __construct(
        private string $orderBy,
        private string $direction,
    ) {}

    /** WooCommerce writes `orderby-direction` (`price-desc`), the direction being optional. */
    public static function of(string $value): self
    {
        $parts = explode(self::DIRECTION_SEPARATOR, strtolower($value));

        return new self($parts[0], $parts[1] ?? self::NO_DIRECTION);
    }

    public function listingCanFollow(): bool
    {
        return $this->order() instanceof CatalogOrder;
    }

    /**
     * @return list<string>
     */
    public function expressions(): array
    {
        $order = $this->order();

        if ($order instanceof CatalogOrder) {
            return $order->expressions(SortDirection::tryFrom($this->direction) ?? $order->defaultDirection());
        }

        return [];
    }

    private function order(): ?CatalogOrder
    {
        return CatalogOrder::tryFrom($this->orderBy);
    }
}
