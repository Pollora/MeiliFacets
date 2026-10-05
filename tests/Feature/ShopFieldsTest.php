<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\ShopFields;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

/** The stock and the kind of document a product's own document carries. */
final class ShopFieldsTest extends TestCase
{
    use KeepsTheIndexOut;

    /** @var list<WC_Product> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Products are WooCommerce\'s.');
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
    public function it_writes_a_simple_product_in_stock_and_of_no_kind(): void
    {
        $fields = $this->fieldsOf($this->simple('instock'));

        $this->assertSame(1, $fields['in_stock']);
        $this->assertArrayNotHasKey('document_kind', $fields);
    }

    #[Test]
    public function it_writes_a_simple_product_out_of_stock(): void
    {
        $this->assertSame(0, $this->fieldsOf($this->simple('outofstock'))['in_stock']);
    }

    #[Test]
    public function it_writes_a_product_with_variants_as_their_parent(): void
    {
        $this->assertSame('parent', $this->fieldsOf($this->variable())['document_kind']);
    }

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
