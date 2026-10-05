<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

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

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Variations are WooCommerce\'s.');
        }

        add_action(self::REINDEX_POST, $this->record(...));
    }

    /** The listener outlives a test: what the cleanup deletes is flushed with nobody listening. */
    protected function tearDown(): void
    {
        remove_action(self::REINDEX_POST, $this->record(...));

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
    public function it_reindexes_nothing_while_no_variation_changed(): void
    {
        $this->changes()->reindexChangedProducts();

        $this->assertSame([], $this->reindexed);
    }

    public function record(int $postId): void
    {
        $this->reindexed[] = $postId;
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
