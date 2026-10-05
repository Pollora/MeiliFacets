<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Pollora\Attributes\Action;

/** Re-indexes the product of every variation changed during the request, once. */
final class VariationChanges
{
    private const string REINDEX_POST = 'meiliscout/reindex_post';

    /** WooCommerce syncs the parents of the variations it saved on `shutdown`, at priority 10. */
    private const int AFTER_PARENT_SYNC = 20;

    /** @var array<int, true> */
    private array $changedProducts = [];

    /** Before a deletion the variation still names its product; it is re-indexed once gone. */
    #[Action('woocommerce_new_product_variation')]
    #[Action('woocommerce_update_product_variation')]
    #[Action('woocommerce_before_delete_product_variation')]
    #[Action('woocommerce_trash_product_variation')]
    public function rememberProductOf(int $variationId): void
    {
        $productId = wp_get_post_parent_id($variationId);

        if ($productId > 0) {
            $this->changedProducts[$productId] = true;
        }
    }

    #[Action('shutdown', priority: self::AFTER_PARENT_SYNC)]
    public function reindexChangedProducts(): void
    {
        foreach (array_keys($this->changedProducts) as $productId) {
            do_action(self::REINDEX_POST, $productId);
        }

        $this->changedProducts = [];
    }
}
