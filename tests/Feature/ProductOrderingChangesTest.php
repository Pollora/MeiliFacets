<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;
use WP_Post;

trait ProductOrderingChangesHelpers
{
    private const string REINDEX_POST = 'meiliscout/reindex_post';

    private const string ORDERING_REQUEST = 'wp_ajax_woocommerce_product_ordering';

    private const array WOOCOMMERCE_HANDLER = ['WC_AJAX', 'product_ordering'];

    private const string AFTER_ORDERING = 'woocommerce_after_product_ordering';

    /** @var list<int> */
    private array $posts = [];

    /** @var list<int> */
    private array $reindexed = [];

    private Closure $recorder;

    /** WooCommerce's own handler leaves the request, unhooked meanwhile: it checks a nonce, then dies. */
    private function order(Closure $writeMenuOrders): void
    {
        $handlerPriority = has_action(self::ORDERING_REQUEST, self::WOOCOMMERCE_HANDLER);
        remove_action(self::ORDERING_REQUEST, self::WOOCOMMERCE_HANDLER);
        add_action(self::ORDERING_REQUEST, $writeMenuOrders);

        try {
            do_action(self::ORDERING_REQUEST);
        } finally {
            remove_action(self::ORDERING_REQUEST, $writeMenuOrders);

            if ($handlerPriority !== false) {
                add_action(self::ORDERING_REQUEST, self::WOOCOMMERCE_HANDLER, $handlerPriority);
            }
        }

        do_action(self::AFTER_ORDERING, 0, []);
    }

    private function publishedPost(): int
    {
        $postId = wp_insert_post(['post_title' => 'Ordering', 'post_status' => 'publish']);
        $this->assertInstanceOf(WP_Post::class, get_post($postId));
        $this->posts[] = $postId;

        return $postId;
    }

    private function record(int $postId): void
    {
        $this->reindexed[] = $postId;
    }
}

uses(TestCase::class, KeepsTheIndexOut::class, ProductOrderingChangesHelpers::class);

beforeEach(function (): void {
    $this->recorder = $this->record(...);
    add_action(self::REINDEX_POST, $this->recorder);
});

afterEach(function (): void {
    remove_action(self::REINDEX_POST, $this->recorder);
    array_walk($this->posts, static fn (int $postId): mixed => wp_delete_post($postId, true));
});

it('reindexes the products the ordering moved once it is done', function (): void {
    $moved = $this->publishedPost();
    $alsoMoved = $this->publishedPost();

    $this->order(static function () use ($moved, $alsoMoved): void {
        clean_post_cache($moved);
        clean_post_cache($alsoMoved);
    });

    expect($this->reindexed)->toBe([$moved, $alsoMoved]);
});

it('a second ordering reindexes only what it moved', function (): void {
    $first = $this->publishedPost();
    $second = $this->publishedPost();
    $this->order(static fn (): mixed => clean_post_cache($first));
    $this->reindexed = [];

    $this->order(static fn (): mixed => clean_post_cache($second));

    expect($this->reindexed)->toBe([$second]);
});

it('a cache cleared outside the ordering reindexes nothing', function (): void {
    clean_post_cache($this->publishedPost());

    do_action(self::AFTER_ORDERING, 0, []);

    expect($this->reindexed)->toBe([]);
});
