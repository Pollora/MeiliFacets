<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\WooCommerce\ShopFields;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

trait ShopFieldsHelpers
{
    /** @var list<WC_Product> */
    private array $created = [];

    private function simple(string $stockStatus): WC_Product_Simple
    {
        $product = new WC_Product_Simple;
        $product->set_name('Shop fields, for the test');
        $product->set_status('publish');
        $product->set_regular_price('12');
        $product->set_stock_status($stockStatus);
        $product->save();

        return $this->created[] = $product;
    }

    private function variable(): WC_Product_Variable
    {
        $product = new WC_Product_Variable;
        $product->set_name('Shop fields of variations, for the test');
        $product->set_status('publish');
        $product->save();
        $this->created[] = $product;

        $variation = new WC_Product_Variation;
        $variation->set_parent_id($product->get_id());
        $variation->set_status('publish');
        $variation->set_regular_price('26');
        $variation->save();
        $this->created[] = $variation;

        WC_Product_Variable::sync($product->get_id());

        return new WC_Product_Variable($product->get_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(WC_Product $product): array
    {
        $post = get_post($product->get_id());
        $this->assertInstanceOf(WP_Post::class, $post);

        return $this->app->make(ShopFields::class)->project($post);
    }
}

/** The stock and the kind of document a product's own document carries. */
uses(TestCase::class, KeepsTheIndexOut::class, ShopFieldsHelpers::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('Products are WooCommerce\'s.');
    }
});

afterEach(function (): void {
    foreach (array_reverse($this->created) as $product) {
        $product->delete(true);
    }
});

it('writes a simple product in stock and of no kind', function (): void {
    $fields = $this->fieldsOf($this->simple('instock'));

    expect($fields['in_stock'])->toBe(1);
    expect($fields)->not->toHaveKey('document_kind');
});

it('writes a simple product out of stock', function (): void {
    expect($this->fieldsOf($this->simple('outofstock'))['in_stock'])->toBe(0);
});

it('writes a product with variants as their parent', function (): void {
    expect($this->fieldsOf($this->variable())['document_kind'])->toBe('parent');
});
