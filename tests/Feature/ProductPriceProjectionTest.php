<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Indexing\ProductPriceProjector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Product;
use WP_Post;

/**
 * Expectations come from WooCommerce itself rather than from this catalogue, so
 * adding a product cannot make the suite wrong.
 */
final class ProductPriceProjectionTest extends TestCase
{
    #[Test]
    public function it_projects_the_ends_of_the_price_rows_woocommerce_wrote(): void
    {
        foreach ($this->products() as $product) {
            $rows = array_map(floatval(...), (array) get_post_meta($product->get_id(), '_price', false));
            $price = $this->project($product);

            $this->assertSame(min($rows), $price[PriceField::Min->value], $this->say($product));
            $this->assertSame(max($rows), $price[PriceField::Max->value], $this->say($product));
        }
    }

    /** A variable or grouped product spans its children, which is the whole point. */
    #[Test]
    public function it_gives_a_product_with_several_prices_an_interval(): void
    {
        $spanning = array_filter(
            $this->products(),
            fn (WC_Product $product): bool => count((array) get_post_meta($product->get_id(), '_price', false)) > 1
        );

        $this->assertNotEmpty($spanning, 'No product in the catalogue carries more than one price.');

        foreach ($spanning as $product) {
            $price = $this->project($product);

            $this->assertLessThan($price[PriceField::Max->value], $price[PriceField::Min->value], $this->say($product));
        }
    }

    /** `is_on_sale()` reads the children; the `onsale` lookup column does not. */
    #[Test]
    public function it_follows_woocommerce_on_whether_a_product_is_on_sale(): void
    {
        foreach ($this->products() as $product) {
            $this->assertSame(
                $product->is_on_sale(),
                $this->project($product)[PriceField::OnSale->value],
                $this->say($product)
            );
        }
    }

    /** Projecting 0 would make it look free, and a `>= 0` filter would return it. */
    #[Test]
    public function it_projects_nothing_for_a_product_that_carries_no_price(): void
    {
        $product = new \WC_Product_Simple;
        $product->set_name('Priceless, for the test');
        $product->save();

        try {
            $this->assertSame([], $this->project($product));
        } finally {
            $product->delete(true);
        }
    }

    #[Test]
    public function it_projects_nothing_for_a_post_that_is_not_a_product(): void
    {
        $post = get_posts(['post_type' => 'post', 'numberposts' => 1])[0];

        $this->assertSame([], $this->app->make(ProductPriceProjector::class)->project($post));
    }

    /**
     * @return array<string, float|bool>
     */
    private function project(WC_Product $product): array
    {
        $post = get_post($product->get_id());

        $this->assertInstanceOf(WP_Post::class, $post);

        return $this->app->make(ProductPriceProjector::class)->project($post);
    }

    /**
     * @return list<WC_Product>
     */
    private function products(): array
    {
        return array_values(array_filter(array_map(
            static fn (int $id): ?WC_Product => wc_get_product($id) ?: null,
            wc_get_products(['limit' => -1, 'return' => 'ids', 'status' => 'publish'])
        )));
    }

    private function say(WC_Product $product): string
    {
        return sprintf('#%d "%s" (%s)', $product->get_id(), $product->get_name(), $product->get_type());
    }
}
