<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Pollora\Attributes\Action;

/** Re-indexes the products the shop's drag-and-drop ordering moved, which no save reports. */
final class ProductOrderingChanges
{
    private const string REINDEX_POST = 'meiliscout/reindex_post';

    private const string ORDERING_REQUEST = 'wp_ajax_woocommerce_product_ordering';

    /** @var array<int, true> */
    private array $movedProducts = [];

    /** WooCommerce clears the cache of the products it moved only, whereas its ordering hooks name every product. */
    #[Action('clean_post_cache')]
    public function rememberMoved(int $postId): void
    {
        if (doing_action(self::ORDERING_REQUEST)) {
            $this->movedProducts[$postId] = true;
        }
    }

    #[Action('woocommerce_after_product_ordering')]
    public function reindexMoved(): void
    {
        foreach (array_keys($this->movedProducts) as $productId) {
            do_action(self::REINDEX_POST, $productId);
        }

        $this->movedProducts = [];
    }
}
