<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class Money
{
    public function of(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        // `wc_price()` returns markup with entities; Blade escapes what it is handed,
        // so they have to become characters here or they reach the page as text.
        return WooCommerce::isActive()
            ? trim($this->plain(wp_strip_all_tags((string) wc_price($value))))
            : number_format($value, 2);
    }

    public function symbol(): string
    {
        return WooCommerce::isActive() ? $this->plain((string) get_woocommerce_currency_symbol()) : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function describe(): ?array
    {
        if (! WooCommerce::isActive()) {
            return null;
        }

        return [
            'format' => $this->plain((string) get_woocommerce_price_format()),
            'symbol' => $this->symbol(),
            'decimals' => wc_get_price_decimals(),
            'decimal' => (string) wc_get_price_decimal_separator(),
            'thousand' => (string) wc_get_price_thousand_separator(),
        ];
    }

    private function plain(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }
}
