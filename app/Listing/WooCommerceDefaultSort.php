<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\CatalogOrder;
use Modules\MeiliFacets\Enums\SortDirection;

/** WooCommerce's default product sorting, read as `WC_Query::get_catalog_ordering_args()` reads it. */
final class WooCommerceDefaultSort
{
    private const string SETTING = 'woocommerce_default_catalog_orderby';

    private const string DIRECTION_SEPARATOR = '-';

    private const int PARTS = 2;

    /** @var list<string>|null */
    private ?array $expressions = null;

    /**
     * @return list<string>
     */
    public function expressions(): array
    {
        return $this->expressions ??= $this->read();
    }

    /**
     * @return list<string>
     */
    private function read(): array
    {
        $setting = $this->setting();
        $order = CatalogOrder::tryFrom($this->orderByIn($setting));

        if ($order instanceof CatalogOrder) {
            $direction = SortDirection::tryFrom($this->directionIn($setting)) ?? $order->defaultDirection();

            return $order->expressions($direction);
        }

        report(CatalogOrderUnsupported::of($setting));

        return [];
    }

    private function setting(): string
    {
        $saved = get_option(self::SETTING, CatalogOrder::MenuOrder->value);

        return strtolower((string) apply_filters(self::SETTING, $saved));
    }

    private function orderByIn(string $setting): string
    {
        return $this->partsOf($setting)[0];
    }

    private function directionIn(string $setting): string
    {
        return $this->partsOf($setting)[1] ?? '';
    }

    /**
     * WooCommerce writes the setting `field-direction` (`price-desc`), the direction being optional.
     *
     * @return array{0: string, 1?: string}
     */
    private function partsOf(string $setting): array
    {
        return explode(self::DIRECTION_SEPARATOR, $setting, self::PARTS);
    }
}
