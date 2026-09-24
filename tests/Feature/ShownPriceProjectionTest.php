<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Indexing\MeiliScoutBridge;
use Modules\MeiliFacets\Indexing\ProductPriceProjector;
use Modules\MeiliFacets\Indexing\ShopTaxLocation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Customer;
use WC_Product;
use WC_Product_Grouped;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

final class ShownPriceProjectionTest extends TestCase
{
    private const string SHOP = 'FR';

    private const string ELSEWHERE = 'BE';

    private const float SHOP_RATE = 20.0;

    private const float ELSEWHERE_RATE = 21.0;

    private const string CALC_TAXES = 'woocommerce_calc_taxes';

    private const string TAX_BASED_ON = 'woocommerce_tax_based_on';

    private const string DEFAULT_COUNTRY = 'woocommerce_default_country';

    private const string PRICES_INCLUDE_TAX = 'woocommerce_prices_include_tax';

    private const string TAX_DISPLAY_SHOP = 'woocommerce_tax_display_shop';

    private const string OPTION_ANSWER = 'pre_option_';

    private const string FIND_RATES = 'woocommerce_find_rates';

    private const string EMPTY_GROUPED_PRICE_HTML = 'woocommerce_grouped_empty_price_html';

    /** WooCommerce's own values, spelled out: its enums only exist from 10.8 and 11.0, above the module's minimum. */
    private const string INCLUSIVE = 'incl';

    private const string EXCLUSIVE = 'excl';

    private const string BASED_ON_SHIPPING = 'shipping';

    /** @var list<WC_Product> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Prices are WooCommerce\'s.');
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
    public function it_indexes_a_price_entered_without_tax_as_the_shop_shows_it_with_tax(): void
    {
        $product = $this->simple('50');

        $this->assertSame([60.0, 60.0], $this->enteredWithoutTaxShownWithTax(fn (): array => $this->range($product)));
    }

    #[Test]
    public function it_indexes_a_price_entered_with_tax_as_the_shop_shows_it_without_tax(): void
    {
        $product = $this->simple('50');
        $range = $this->enteredWithTaxShownWithoutTax(fn (): array => $this->range($product));

        $this->assertEqualsWithDelta(41.666667, $range[0], 0.000001);
    }

    #[Test]
    public function it_indexes_the_sale_price_a_product_on_sale_is_billed(): void
    {
        $product = $this->simple('50', sale: '40');

        $this->assertSame([48.0, 48.0], $this->enteredWithoutTaxShownWithTax(fn (): array => $this->range($product)));
    }

    #[Test]
    public function it_indexes_the_range_a_variable_product_shows(): void
    {
        $product = $this->variable(['10', '20']);

        $this->assertSame([12.0, 24.0], $this->enteredWithoutTaxShownWithTax(fn (): array => $this->range($product)));
    }

    #[Test]
    public function it_indexes_the_range_a_grouped_product_shows(): void
    {
        $product = $this->grouped(['10', '20']);

        $this->assertSame([12.0, 24.0], $this->enteredWithoutTaxShownWithTax(fn (): array => $this->range($product)));
    }

    #[Test]
    public function it_skips_a_grouped_child_without_a_price_as_the_card_does(): void
    {
        $product = $this->grouped(['', '10', '20']);

        $this->assertSame([12.0, 24.0], $this->enteredWithoutTaxShownWithTax(fn (): array => $this->range($product)));
    }

    #[Test]
    public function it_indexes_no_price_for_a_grouped_product_whose_card_shows_none(): void
    {
        $this->assertSame([], $this->projected($this->grouped(['', ''])));
    }

    #[Test]
    public function it_indexes_no_price_for_a_grouped_product_a_theme_labels_instead(): void
    {
        $product = $this->grouped(['', '']);
        $label = static fn (): string => 'Price on request';
        add_filter(self::EMPTY_GROUPED_PRICE_HTML, $label);

        try {
            $this->assertSame([], $this->projected($product));
        } finally {
            remove_filter(self::EMPTY_GROUPED_PRICE_HTML, $label);
        }
    }

    #[Test]
    public function it_keeps_a_grouped_product_of_free_children_at_zero(): void
    {
        $this->assertSame([0.0, 0.0], $this->range($this->grouped(['0', '0'])));
    }

    #[Test]
    public function it_taxes_price_and_card_at_the_shop_address_whoever_is_in_session(): void
    {
        $product = $this->simple('50');
        $bridge = $this->app->make(MeiliScoutBridge::class);
        $customer = WC()->customer;
        WC()->customer = new WC_Customer;
        WC()->customer->set_shipping_country(self::ELSEWHERE);
        WC()->customer->set_billing_country(self::ELSEWHERE);

        try {
            $document = $this->enteredWithoutTaxShownWithTax(fn (): array => [
                ...$bridge->addPrice([], $this->postOf($product)),
                ...$bridge->addCard([], $this->postOf($product)),
            ]);
        } finally {
            WC()->customer = $customer;
        }

        $this->assertSame(60.0, $document[DocumentField::Price->value][PriceField::Min->value]);
        $this->assertSame(
            wp_strip_all_tags(wc_price(60)),
            wp_strip_all_tags((string) $document[DocumentField::Card->value][CardField::Price->value])
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function enteredWithoutTaxShownWithTax(Closure $read): mixed
    {
        return $this->underTax([
            self::PRICES_INCLUDE_TAX => wc_bool_to_string(false),
            self::TAX_DISPLAY_SHOP => self::INCLUSIVE,
        ], $read);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function enteredWithTaxShownWithoutTax(Closure $read): mixed
    {
        return $this->underTax([
            self::PRICES_INCLUDE_TAX => wc_bool_to_string(true),
            self::TAX_DISPLAY_SHOP => self::EXCLUSIVE,
        ], $read);
    }

    /**
     * @template T
     *
     * @param  array<string, string>  $entryAndDisplay
     * @param  Closure(): T  $read
     * @return T
     */
    private function underTax(array $entryAndDisplay, Closure $read): mixed
    {
        return $this->withOptions([
            self::CALC_TAXES => wc_bool_to_string(true),
            self::TAX_BASED_ON => self::BASED_ON_SHIPPING,
            self::DEFAULT_COUNTRY => self::SHOP,
            ...$entryAndDisplay,
        ], fn (): mixed => $this->withRates($read));
    }

    /**
     * @template T
     *
     * @param  array<string, string>  $options
     * @param  Closure(): T  $read
     * @return T
     */
    private function withOptions(array $options, Closure $read): mixed
    {
        $answers = array_map(static fn (string $value): Closure => static fn (): string => $value, $options);

        foreach ($answers as $option => $answer) {
            add_filter(self::OPTION_ANSWER.$option, $answer);
        }

        try {
            return $read();
        } finally {
            foreach ($answers as $option => $answer) {
                remove_filter(self::OPTION_ANSWER.$option, $answer);
            }
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function withRates(Closure $read): mixed
    {
        $rates = $this->rates(...);
        add_filter(self::FIND_RATES, $rates, accepted_args: 2);

        try {
            return $read();
        } finally {
            remove_filter(self::FIND_RATES, $rates);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $matched
     * @param  array<string, string>  $where
     * @return array<int, array<string, mixed>>
     */
    private function rates(array $matched, array $where): array
    {
        $rate = $where['country'] === self::SHOP ? self::SHOP_RATE : self::ELSEWHERE_RATE;

        return [1 => [
            'rate' => $rate,
            'label' => 'VAT',
            'shipping' => wc_bool_to_string(true),
            'compound' => wc_bool_to_string(false),
        ]];
    }

    private function simple(string $price, string $sale = ''): WC_Product_Simple
    {
        $product = new WC_Product_Simple;
        $product->set_name('Shown price, for the test');
        $product->set_status('publish');
        $product->set_regular_price($price);
        $product->set_sale_price($sale);
        $product->save();

        return $this->created[] = $product;
    }

    /**
     * @param  list<string>  $prices
     */
    private function variable(array $prices): WC_Product_Variable
    {
        $product = new WC_Product_Variable;
        $product->set_name('Shown range, for the test');
        $product->set_status('publish');
        $product->save();
        $this->created[] = $product;

        foreach ($prices as $price) {
            $variation = new WC_Product_Variation;
            $variation->set_parent_id($product->get_id());
            $variation->set_status('publish');
            $variation->set_regular_price($price);
            $variation->save();
            $this->created[] = $variation;
        }

        WC_Product_Variable::sync($product->get_id());

        return new WC_Product_Variable($product->get_id());
    }

    /**
     * @param  list<string>  $prices
     */
    private function grouped(array $prices): WC_Product_Grouped
    {
        $children = array_map(fn (string $price): int => $this->simple($price)->get_id(), $prices);

        $product = new WC_Product_Grouped;
        $product->set_name('Shown group, for the test');
        $product->set_status('publish');
        $product->set_children($children);
        $product->save();

        return $this->created[] = $product;
    }

    /**
     * @return array{float, float}
     */
    private function range(WC_Product $product): array
    {
        $price = $this->projected($product);

        return [$price[PriceField::Min->value], $price[PriceField::Max->value]];
    }

    /**
     * @return array<string, float|bool>
     */
    private function projected(WC_Product $product): array
    {
        $projector = $this->app->make(ProductPriceProjector::class);

        return $this->app->make(ShopTaxLocation::class)->during(
            fn (): array => $projector->project($this->postOf($product))
        );
    }

    private function postOf(WC_Product $product): WP_Post
    {
        $post = get_post($product->get_id());

        $this->assertInstanceOf(WP_Post::class, $post);

        return $post;
    }
}
