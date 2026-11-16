<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\ProductMeta;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductPriceProjector;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;
use WC_Data_Store;
use WC_Product;
use WC_Product_Simple;
use WP_Post;

trait ProductPriceProjectionHelpers
{
    private const string AMOUNT = '.woocommerce-Price-amount';

    private const string NOT_BILLED = 'del, .woocommerce-price-suffix';

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

/**
 * Expectations come from WooCommerce itself rather than from this catalogue, so
 * adding a product cannot make the suite wrong.
 */
uses(TestCase::class, KeepsTheIndexOut::class, ProductPriceProjectionHelpers::class);

it('indexes the prices the card bills', function (): void {
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
});

/** A variable or grouped product spans its children, which is the whole point. */
it('gives a product with several prices an interval', function (): void {
    $spanning = array_filter(
        $this->products(),
        fn (WC_Product $product): bool => count($this->distinctPrices($product)) > 1
    );

    $this->assertNotEmpty($spanning, 'No product in the catalogue carries more than one price.');

    foreach ($spanning as $product) {
        $price = $this->project($product);

        $this->assertLessThan($price[PriceField::Max->value], $price[PriceField::Min->value], $this->say($product));
    }
});

/** WooCommerce's own list: a variation on sale lists its parent, a grouped product is never listed. */
it('puts on sale exactly what woocommerce lists as on sale', function (): void {
    $rows = WC_Data_Store::load('product')->get_on_sale_products();
    $listed = [...array_column($rows, 'id'), ...array_column($rows, 'parent_id')];

    foreach ($this->products() as $product) {
        $this->assertSame(
            in_array((string) $product->get_id(), array_map(strval(...), $listed), true),
            $this->project($product)[PriceField::OnSale->value] ?? false,
            $this->say($product)
        );
    }
});

/** R-112: the sale has begun by its dates, but the price billed has not switched yet. */
it('leaves off sale a product still billed at full price', function (): void {
    $product = new WC_Product_Simple;
    $product->set_name('Sale not switched yet, for the test');
    $product->set_regular_price('30');
    $product->set_sale_price('20');
    $product->save();
    update_post_meta($product->get_id(), ProductMeta::Price->value, '30');

    try {
        expect(wc_get_product($product->get_id())->is_on_sale())->toBeTrue();
        expect($this->project(wc_get_product($product->get_id()))[PriceField::OnSale->value])->toBeFalse();
    } finally {
        $product->delete(true);
    }
});

/** Projecting 0 would make it look free, and a `>= 0` filter would return it. */
it('projects nothing for a product that carries no price', function (): void {
    $product = new WC_Product_Simple;
    $product->set_name('Priceless, for the test');
    $product->save();

    try {
        expect($this->project($product))->toBe([]);
    } finally {
        $product->delete(true);
    }
});

it('projects nothing for a product whose price was emptied', function (): void {
    $product = new WC_Product_Simple;
    $product->set_name('Price emptied, for the test');
    $product->set_regular_price('30');
    $product->save();
    $product->set_regular_price('');
    $product->save();

    try {
        expect(get_post_meta($product->get_id(), ProductMeta::Price->value, false))->toBe(['']);
        expect($this->project($product))->toBe([]);
    } finally {
        $product->delete(true);
    }
});

it('projects nothing for a price row left null', function (): void {
    $product = new WC_Product_Simple;
    $product->set_name('Price left null, for the test');
    $product->set_regular_price('30');
    $product->save();
    update_post_meta($product->get_id(), ProductMeta::Price->value, null);

    try {
        expect(get_post_meta($product->get_id(), ProductMeta::Price->value, false))->toBe([null]);
        expect($this->project($product))->toBe([]);
    } finally {
        $product->delete(true);
    }
});

it('keeps a free product at zero', function (): void {
    $product = new WC_Product_Simple;
    $product->set_name('Free, for the test');
    $product->set_regular_price('0');
    $product->save();

    try {
        $price = $this->project($product);

        expect($price[PriceField::Min->value])->toBe(0.0);
        expect($price[PriceField::Max->value])->toBe(0.0);
    } finally {
        $product->delete(true);
    }
});

it('projects nothing for a post that is not a product', function (): void {
    $post = get_posts(['post_type' => 'post', 'numberposts' => 1])[0];

    expect($this->app->make(ProductPriceProjector::class)->project($post))->toBe([]);
});
