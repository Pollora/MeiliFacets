<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Indexing\VariationChanges;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;

/** A variation that changes on its own re-indexes its product, once per request. */
final class VariationChangesTest extends TestCase
{
    use KeepsTheIndexOut;

    private const string REINDEX_POST = 'meiliscout/reindex_post';

    /** @var list<WC_Product> */
    private array $created = [];

    /** @var list<int> */
    private array $reindexed = [];

    /** @var list<int> */
    private array $variationsAtReindex = [];

    /** Each `$this->method(...)` is a new closure, which `remove_action()` would not find. */
    private Closure $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Variations are WooCommerce\'s.');
        }

        $this->recorder = $this->record(...);
        add_action(self::REINDEX_POST, $this->recorder);
    }

    /** The listener outlives a test: what the cleanup deletes is flushed with nobody listening. */
    protected function tearDown(): void
    {
        if (isset($this->recorder)) {
            remove_action(self::REINDEX_POST, $this->recorder);
        }

        foreach (array_reverse($this->created) as $product) {
            $product->delete(true);
        }

        $this->changes()->reindexChangedProducts();

        parent::tearDown();
    }

    #[Test]
    public function it_reindexes_a_product_once_however_many_of_its_variations_change(): void
    {
        [$product, $small, $large] = $this->variable();
        $this->reindexed = [];

        wc_update_product_stock_status($large->get_id(), 'outofstock');
        wc_update_product_stock_status($small->get_id(), 'outofstock');
        $this->changes()->reindexChangedProducts();

        $this->assertSame([$product->get_id()], $this->reindexed);
    }

    #[Test]
    public function it_reindexes_the_product_of_a_deleted_variation(): void
    {
        [$product, $small] = $this->variable();
        $this->reindexed = [];

        $small->delete(true);
        $this->changes()->reindexChangedProducts();

        $this->assertSame([$product->get_id()], $this->reindexed);
    }

    #[Test]
    public function it_reindexes_a_restored_product_once_its_variations_are_back(): void
    {
        [$product] = $this->variable();
        wp_trash_post($product->get_id());
        $this->changes()->reindexChangedProducts();
        $this->reindexed = [];
        $this->variationsAtReindex = [];

        wp_untrash_post($product->get_id());
        $this->changes()->reindexChangedProducts();

        $this->assertSame([$product->get_id()], $this->reindexed);
        $this->assertSame([2], $this->variationsAtReindex);
    }

    #[Test]
    public function it_reindexes_the_product_of_a_variation_whose_meta_is_written_directly(): void
    {
        [$product, $small] = $this->variable();
        $this->reindexed = [];

        update_post_meta($small->get_id(), '_price', '19');
        $this->changes()->reindexChangedProducts();

        $this->assertSame([$product->get_id()], $this->reindexed);
    }

    #[Test]
    public function it_reindexes_nothing_for_the_meta_of_a_child_that_is_not_a_variation(): void
    {
        [$product] = $this->variable();
        $attachment = ['post_title' => 'Variation changes, for the test', 'post_mime_type' => 'image/jpeg'];
        $attachmentId = wp_insert_attachment($attachment, false, $product->get_id());
        $this->reindexed = [];

        update_post_meta($attachmentId, '_meilifacets_test', '1');
        $this->changes()->reindexChangedProducts();
        wp_delete_attachment($attachmentId, true);

        $this->assertSame([], $this->reindexed);
    }

    #[Test]
    public function it_reindexes_nothing_for_a_meta_deleted_from_every_post_while_a_variation_is_the_global_post(): void
    {
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

        $this->assertSame([], $this->reindexed);
    }

    #[Test]
    public function it_reindexes_nothing_while_no_variation_changed(): void
    {
        $this->changes()->reindexChangedProducts();

        $this->assertSame([], $this->reindexed);
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
