<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing\WooCommerce;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Support\WooCommerce;
use WC_Product;
use WP_Post;

/** What « Popularity (sales) » and « Average rating » rank a product on, as `WC_Query` reads it. */
final readonly class ProductPopularity
{
    /**
     * @return array<string, int|float>
     */
    public function project(WP_Post $post): array
    {
        if (! WooCommerce::isActive()) {
            return [];
        }

        $product = wc_get_product($post);

        if (! $product instanceof WC_Product) {
            return [];
        }

        return [
            DocumentField::TotalSales->value => (int) $product->get_total_sales(),
            DocumentField::AverageRating->value => (float) $product->get_average_rating(),
            DocumentField::RatingCount->value => (int) $product->get_rating_count(),
        ];
    }
}
