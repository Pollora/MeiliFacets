<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductStock;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

trait ProductStockHelpers
{
    private const string IN_STOCK = 'instock';

    private const string OUT_OF_STOCK = 'outofstock';

    private const array FLAGGED = [CardField::OutOfStock->value => true];

    /** @var list<WC_Product> */
    private array $created = [];

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

/** The out-of-stock flag the module sets on a product's card, whatever its type. */
uses(TestCase::class, KeepsTheIndexOut::class, ProductStockHelpers::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('Stock is WooCommerce\'s.');
    }
});

afterEach(function (): void {
    foreach (array_reverse($this->created) as $product) {
        $product->delete(true);
    }
});

it('flags a simple product out of stock', function (): void {
    expect($this->stockOf($this->simple(self::OUT_OF_STOCK)))->toBe(self::FLAGGED);
});

it('leaves a simple product in stock unflagged', function (): void {
    expect($this->stockOf($this->simple(self::IN_STOCK)))->toBe([]);
});

it('flags a variable product whose every variation is out of stock', function (): void {
    $product = $this->variable([self::OUT_OF_STOCK, self::OUT_OF_STOCK]);

    expect($this->stockOf($product))->toBe(self::FLAGGED);
});

it('leaves a variable product with one variation in stock unflagged', function (): void {
    $product = $this->variable([self::OUT_OF_STOCK, self::IN_STOCK]);

    expect($this->stockOf($product))->toBe([]);
});

it('flags nothing on a post', function (): void {
    $post = new WP_Post((object) ['ID' => 0, 'post_type' => 'post']);

    expect($this->app->make(ProductStock::class)->project($post))->toBe([]);
});
