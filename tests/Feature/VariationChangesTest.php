<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Indexing\WooCommerce\VariationChanges;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;

trait VariationChangesHelpers
{
    private const string REINDEX_POST = 'meiliscout/reindex_post';

    /** @var list<WC_Product> */
    private array $created = [];

    /** @var list<int> */
    private array $reindexed = [];

    /** @var list<int> */
    private array $variationsAtReindex = [];

    /** Each `$this->method(...)` is a new closure, which `remove_action()` would not find. */
    private Closure $recorder;

    private function forgetTheRecorder(): void
    {
        if (isset($this->recorder)) {
            remove_action(self::REINDEX_POST, $this->recorder);
        }
    }

    private function record(int $postId): void
    {
        $this->reindexed[] = $postId;
        $product = wc_get_product($postId);
        $this->variationsAtReindex[] = $product instanceof WC_Product ? count($product->get_children()) : 0;
    }

    private function changes(): VariationChanges
    {
        return $this->app->make(VariationChanges::class);
    }

    /**
     * @return array{WC_Product_Variable, WC_Product_Variation, WC_Product_Variation}
     */
    private function variable(): array
    {
        $product = new WC_Product_Variable;
        $product->set_name('Variation changes, for the test');
        $product->set_status('publish');
        $product->save();
        $this->created[] = $product;

        $variations = array_map(
            fn (string $price): WC_Product_Variation => $this->variation($product, $price),
            ['26', '39']
        );
        $this->changes()->reindexChangedProducts();

        return [$product, ...$variations];
    }

    private function variation(WC_Product_Variable $product, string $price): WC_Product_Variation
    {
        $variation = new WC_Product_Variation;
        $variation->set_parent_id($product->get_id());
        $variation->set_status('publish');
        $variation->set_regular_price($price);
        $variation->save();

        return $this->created[] = $variation;
    }
}

/** A variation that changes on its own re-indexes its product, once per request. */
uses(TestCase::class, KeepsTheIndexOut::class, VariationChangesHelpers::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('Variations are WooCommerce\'s.');
    }

    $this->recorder = $this->record(...);
    add_action(self::REINDEX_POST, $this->recorder);
});

/** The listener outlives a test: what the cleanup deletes is flushed with nobody listening. */
afterEach(function (): void {
    $this->forgetTheRecorder();

    foreach (array_reverse($this->created) as $product) {
        $product->delete(true);
    }

    $this->changes()->reindexChangedProducts();
});

it('reindexes a product once however many of its variations change', function (): void {
    [$product, $small, $large] = $this->variable();
    $this->reindexed = [];

    wc_update_product_stock_status($large->get_id(), 'outofstock');
    wc_update_product_stock_status($small->get_id(), 'outofstock');
    $this->changes()->reindexChangedProducts();

    expect($this->reindexed)->toBe([$product->get_id()]);
});

it('reindexes the product of a deleted variation', function (): void {
    [$product, $small] = $this->variable();
    $this->reindexed = [];

    $small->delete(true);
    $this->changes()->reindexChangedProducts();

    expect($this->reindexed)->toBe([$product->get_id()]);
});

it('reindexes a restored product once its variations are back', function (): void {
    [$product] = $this->variable();
    wp_trash_post($product->get_id());
    $this->changes()->reindexChangedProducts();
    $this->reindexed = [];
    $this->variationsAtReindex = [];

    wp_untrash_post($product->get_id());
    $this->changes()->reindexChangedProducts();

    expect($this->reindexed)->toBe([$product->get_id()]);
    expect($this->variationsAtReindex)->toBe([2]);
});

it('reindexes the product of a variation whose meta is written directly', function (): void {
    [$product, $small] = $this->variable();
    $this->reindexed = [];

    update_post_meta($small->get_id(), '_price', '19');
    $this->changes()->reindexChangedProducts();

    expect($this->reindexed)->toBe([$product->get_id()]);
});

it('reindexes nothing for the meta of a child that is not a variation', function (): void {
    [$product] = $this->variable();
    $attachment = ['post_title' => 'Variation changes, for the test', 'post_mime_type' => 'image/jpeg'];
    $attachmentId = wp_insert_attachment($attachment, false, $product->get_id());
    $this->reindexed = [];

    update_post_meta($attachmentId, '_meilifacets_test', '1');
    $this->changes()->reindexChangedProducts();
    wp_delete_attachment($attachmentId, true);

    expect($this->reindexed)->toBe([]);
});

it('reindexes nothing for a meta deleted from every post while a variation is the global post', function (): void {
    global $post;

    [$product, $small] = $this->variable();
    update_post_meta($product->get_id(), '_meilifacets_test', '1');
    $current = $post;
    $post = get_post($small->get_id());
    $this->reindexed = [];

    try {
        delete_post_meta_by_key('_meilifacets_test');
        $this->changes()->reindexChangedProducts();
    } finally {
        $post = $current;
    }

    expect($this->reindexed)->toBe([]);
});

it('reindexes nothing while no variation changed', function (): void {
    $this->changes()->reindexChangedProducts();

    expect($this->reindexed)->toBe([]);
});
