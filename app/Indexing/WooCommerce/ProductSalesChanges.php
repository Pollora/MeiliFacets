<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing\WooCommerce;

use Pollora\Attributes\Action;

/** Re-indexes the product whose sales an order just changed, which WooCommerce writes in SQL and never saves. */
final readonly class ProductSalesChanges
{
    private const string REINDEX_POST = 'meiliscout/reindex_post';

    #[Action('woocommerce_updated_product_sales')]
    public function reindex(int $productId): void
    {
        do_action(self::REINDEX_POST, $productId);
    }
}
