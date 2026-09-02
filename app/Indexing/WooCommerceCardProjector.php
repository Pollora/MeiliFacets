<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use WC_Product;
use WP_Post;

final readonly class WooCommerceCardProjector implements CardProjector
{
    public function __construct(private CardProjector $card) {}

    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array
    {
        return [
            ...$this->card->project($post),
            ...$this->price($post),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function price(WP_Post $post): array
    {
        $product = wc_get_product($post);

        if (! $product instanceof WC_Product) {
            return [];
        }

        return [CardField::Price->value => (string) $product->get_price_html()];
    }
}
