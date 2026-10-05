<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use WC_Product;
use WP_Post;

final readonly class WooCommerceCardProjector implements CardProjector
{
    public const string DEFAULT_IMAGE_SIZE = 'woocommerce_thumbnail';

    public function __construct(private CardProjector $productCard, private CardProjector $otherCard) {}

    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array
    {
        $product = wc_get_product($post);

        if (! $product instanceof WC_Product) {
            return $this->otherCard->project($post);
        }

        return [
            ...$this->productCard->project($post),
            CardField::Price->value => (string) $product->get_price_html(),
        ];
    }
}
