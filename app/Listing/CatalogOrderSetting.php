<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\CatalogOrder;
use Modules\MeiliFacets\Enums\SortDirection;

/** A WooCommerce catalog sorting setting, read as `WC_Query::get_catalog_ordering_args()` reads it. */
final readonly class CatalogOrderSetting
{
    private const string DIRECTION_SEPARATOR = '-';

    private const int PARTS = 2;

    private const int FIELD = 0;

    private const int DIRECTION = 1;

    private const string MISSING = '';

    private function __construct(
        private string $field,
        private string $direction,
    ) {}

    /**
     * WooCommerce writes `field-direction` (`price-desc`), the direction being optional; a filter may answer both
     * parts as an array.
     */
    public static function of(mixed $value): self
    {
        $parts = self::partsOf($value);

        return new self(self::partAt($parts, self::FIELD), self::partAt($parts, self::DIRECTION));
    }

    public function isReproducible(): bool
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
        return CatalogOrder::tryFrom($this->field);
    }

    /**
     * @return list<mixed>
     */
    private static function partsOf(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_scalar($value)) {
            return explode(self::DIRECTION_SEPARATOR, (string) $value, self::PARTS);
        }

        return [];
    }

    /**
     * @param  list<mixed>  $parts
     */
    private static function partAt(array $parts, int $position): string
    {
        $part = $parts[$position] ?? self::MISSING;

        return is_scalar($part) ? strtolower((string) $part) : self::MISSING;
    }
}
