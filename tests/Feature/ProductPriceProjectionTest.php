<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\ProductMeta;
use Modules\MeiliFacets\Indexing\ProductPriceProjector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Data_Store;
use WC_Product;
use WC_Product_Simple;
use WP_Post;

/**
 * Expectations come from WooCommerce itself rather than from this catalogue, so
 * adding a product cannot make the suite wrong.
 */
final class ProductPriceProjectionTest extends TestCase
{
    private const string AMOUNT = '.woocommerce-Price-amount';

    private const string NOT_BILLED = 'del, .woocommerce-price-suffix';

    #[Test]
    public function it_indexes_the_prices_the_card_bills(): void
    {
        foreach ($this->products() as $product) {
            $billed = $this->billedAmounts($product);
            $price = $this->project($product);

            if ($billed === []) {
                $this->assertSame([], $price, $this->say($product));

                continue;
            }

            $this->assertNotSame([], $price, $this->say($product));
            foreach ([PriceField::Min, PriceField::Max] as $end) {
                $this->assertContains($this->plain(wc_price($price[$end->value])), $billed, $this->say($product));
            }
        }
    }

    /** A variable or grouped product spans its children, which is the whole point. */
    #[Test]
    public function it_gives_a_product_with_several_prices_an_interval(): void
    {
        $spanning = array_filter(
            $this->products(),
            fn (WC_Product $product): bool => count($this->distinctPrices($product)) > 1
        );

        $this->assertNotEmpty($spanning, 'No product in the catalogue carries more than one price.');

        foreach ($spanning as $product) {
            $price = $this->project($product);

            $this->assertLessThan($price[PriceField::Max->value], $price[PriceField::Min->value], $this->say($product));
        }
    }

    /** WooCommerce's own list: a variation on sale lists its parent, a grouped product is never listed. */
    #[Test]
    public function it_puts_on_sale_exactly_what_woocommerce_lists_as_on_sale(): void
    {
        $rows = WC_Data_Store::load('product')->get_on_sale_products();
        $listed = [...array_column($rows, 'id'), ...array_column($rows, 'parent_id')];

        foreach ($this->products() as $product) {
            $this->assertSame(
                in_array((string) $product->get_id(), array_map(strval(...), $listed), true),
                $this->project($product)[PriceField::OnSale->value] ?? false,
                $this->say($product)
            );
        }
    }

    /** R-112: the sale has begun by its dates, but the price billed has not switched yet. */
    #[Test]
    public function it_leaves_off_sale_a_product_still_billed_at_full_price(): void
    {
        $product = new WC_Product_Simple;
        $product->set_name('Sale not switched yet, for the test');
        $product->set_regular_price('30');
        $product->set_sale_price('20');
        $product->save();
        update_post_meta($product->get_id(), ProductMeta::Price->value, '30');

        try {
            $this->assertTrue(wc_get_product($product->get_id())->is_on_sale());
            $this->assertFalse($this->project(wc_get_product($product->get_id()))[PriceField::OnSale->value]);
        } finally {
            $product->delete(true);
        }
    }

    /** Projecting 0 would make it look free, and a `>= 0` filter would return it. */
    #[Test]
    public function it_projects_nothing_for_a_product_that_carries_no_price(): void
    {
        $product = new WC_Product_Simple;
        $product->set_name('Priceless, for the test');
        $product->save();

        try {
            $this->assertSame([], $this->project($product));
        } finally {
            $product->delete(true);
        }
    }

    #[Test]
    public function it_projects_nothing_for_a_product_whose_price_was_emptied(): void
    {
        $product = new WC_Product_Simple;
        $product->set_name('Price emptied, for the test');
        $product->set_regular_price('30');
        $product->save();
        $product->set_regular_price('');
        $product->save();

        try {
            $this->assertSame([''], get_post_meta($product->get_id(), ProductMeta::Price->value, false));
            $this->assertSame([], $this->project($product));
        } finally {
            $product->delete(true);
        }
    }

    #[Test]
    public function it_projects_nothing_for_a_price_row_left_null(): void
    {
        $product = new WC_Product_Simple;
        $product->set_name('Price left null, for the test');
        $product->set_regular_price('30');
        $product->save();
        update_post_meta($product->get_id(), ProductMeta::Price->value, null);

        try {
            $this->assertSame([null], get_post_meta($product->get_id(), ProductMeta::Price->value, false));
            $this->assertSame([], $this->project($product));
        } finally {
            $product->delete(true);
        }
    }

    #[Test]
    public function it_keeps_a_free_product_at_zero(): void
    {
        $product = new WC_Product_Simple;
        $product->set_name('Free, for the test');
        $product->set_regular_price('0');
        $product->save();

        try {
            $price = $this->project($product);

            $this->assertSame(0.0, $price[PriceField::Min->value]);
            $this->assertSame(0.0, $price[PriceField::Max->value]);
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
     * @return list<mixed>
     */
    private function distinctPrices(WC_Product $product): array
    {
        return array_values(array_unique((array) get_post_meta($product->get_id(), ProductMeta::Price->value, false)));
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

    private function plain(string $html): string
    {
        return html_entity_decode(wp_strip_all_tags($html));
    }

    /**
     * A card on sale also shows its regular price, struck through, and a price suffix may show the other side of
     * the tax.
     *
     * @return list<string>
     */
    private function billedAmounts(WC_Product $product): array
    {
        $card = HTMLDocument::createFromString('<div>'.$product->get_price_html().'</div>', LIBXML_NOERROR);

        foreach ($card->querySelectorAll(self::NOT_BILLED) as $notBilled) {
            $notBilled->remove();
        }

        return array_map(
            static fn (Element $amount): string => (string) $amount->textContent,
            iterator_to_array($card->querySelectorAll(self::AMOUNT), preserve_keys: false)
        );
    }

    private function say(WC_Product $product): string
    {
        return sprintf('#%d "%s" (%s)', $product->get_id(), $product->get_name(), $product->get_type());
    }
}
