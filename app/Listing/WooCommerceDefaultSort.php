<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\CatalogOrder;

final class WooCommerceDefaultSort
{
    private const string SETTING = 'woocommerce_default_catalog_orderby';

    /** @var list<string>|null */
    private ?array $expressions = null;

    /**
     * @return list<string>
     */
    public function expressions(): array
    {
        return $this->expressions ??= $this->setting()->expressions();
    }

    private function setting(): CatalogOrderSetting
    {
        $saved = get_option(self::SETTING, CatalogOrder::MenuOrder->value);

        return CatalogOrderSetting::of((string) apply_filters(self::SETTING, $saved));
    }
}
