<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Indexing\ProductStock;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

/** The out-of-stock flag the module sets on a product's card, whatever its type. */
final class ProductStockTest extends TestCase
{
    use KeepsTheIndexOut;

    private const string IN_STOCK = 'instock';

    private const string OUT_OF_STOCK = 'outofstock';

    private const array FLAGGED = [CardField::OutOfStock->value => true];

    /** @var list<WC_Product> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Stock is WooCommerce\'s.');
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $product) {
            $product->delete(true);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_flags_a_simple_product_out_of_stock(): void
    {
        $this->assertSame(self::FLAGGED, $this->stockOf($this->simple(self::OUT_OF_STOCK)));
    }

    #[Test]
    public function it_leaves_a_simple_product_in_stock_unflagged(): void
    {
        $this->assertSame([], $this->stockOf($this->simple(self::IN_STOCK)));
    }

    #[Test]
    public function it_flags_a_variable_product_whose_every_variation_is_out_of_stock(): void
    {
        $product = $this->variable([self::OUT_OF_STOCK, self::OUT_OF_STOCK]);

        $this->assertSame(self::FLAGGED, $this->stockOf($product));
    }

    #[Test]
    public function it_leaves_a_variable_product_with_one_variation_in_stock_unflagged(): void
    {
        $product = $this->variable([self::OUT_OF_STOCK, self::IN_STOCK]);

        $this->assertSame([], $this->stockOf($product));
    }

    #[Test]
    public function it_flags_nothing_on_a_post(): void
    {
        $post = new WP_Post((object) ['ID' => 0, 'post_type' => 'post']);

        $this->assertSame([], $this->app->make(ProductStock::class)->project($post));
    }

    private function simple(string $stockStatus): WC_Product_Simple
    {
        $product = new WC_Product_Simple;
        $product->set_name('Stock, for the test');
        $product->set_status('publish');
        $product->set_regular_price('12');
        $product->set_stock_status($stockStatus);
        $product->save();

        return $this->created[] = $product;
    }

    /**
     * @param  list<string>  $stockStatuses
     */
    private function variable(array $stockStatuses): WC_Product_Variable
    {
        $product = new WC_Product_Variable;
        $product->set_name('Stock of variations, for the test');
        $product->set_status('publish');
        $product->save();
        $this->created[] = $product;

        foreach ($stockStatuses as $stockStatus) {
            $variation = new WC_Product_Variation;
            $variation->set_parent_id($product->get_id());
            $variation->set_status('publish');
            $variation->set_regular_price('26');
            $variation->set_stock_status($stockStatus);
            $variation->save();
            $this->created[] = $variation;
        }

        WC_Product_Variable::sync($product->get_id());

        return new WC_Product_Variable($product->get_id());
    }

    /**
     * @return array<string, true>
     */
    private function stockOf(WC_Product $product): array
    {
        $post = get_post($product->get_id());
        $this->assertInstanceOf(WP_Post::class, $post);

        return $this->app->make(ProductStock::class)->project($post);
    }
}
