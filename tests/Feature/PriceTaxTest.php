<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\PriceTax;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shop under test sells tax-free, so the active path has to be set up here.
 * Every option is put back, including the rate table.
 */
final class PriceTaxTest extends TestCase
{
    /** @var array<string, string> */
    private array $options = [];

    protected function tearDown(): void
    {
        foreach ($this->options as $option => $value) {
            update_option($option, $value);
        }

        \WC_Tax::_delete_tax_rate($this->rate ?? 0);

        parent::tearDown();
    }

    private ?int $rate = null;

    #[Test]
    public function it_leaves_the_range_alone_in_a_shop_without_tax(): void
    {
        $range = PriceTax::excluding(new Range(40.0, 70.0));

        $this->assertSame(40.0, $range->min);
        $this->assertSame(70.0, $range->max);
    }

    /** What the visitor typed is inclusive of tax; the index holds the price without it. */
    #[Test]
    public function it_takes_the_tax_back_off_when_a_shop_shows_prices_inclusive(): void
    {
        $this->sellingWithTax(20.0);

        $range = PriceTax::excluding(new Range(120.0, 240.0));

        $this->assertEqualsWithDelta(100.0, $range->min, 0.01);
        $this->assertEqualsWithDelta(200.0, $range->max, 0.01);
    }

    #[Test]
    public function it_leaves_an_open_end_open(): void
    {
        $this->sellingWithTax(20.0);

        $range = PriceTax::excluding(new Range(min: 120.0));

        $this->assertEqualsWithDelta(100.0, $range->min, 0.01);
        $this->assertNull($range->max);
    }

    /** Displaying prices the way they are stored needs no conversion at all. */
    #[Test]
    public function it_leaves_the_range_alone_when_the_shop_shows_them_as_stored(): void
    {
        $this->sellingWithTax(20.0);
        $this->option('woocommerce_tax_display_shop', 'excl');

        $this->assertSame(120.0, PriceTax::excluding(new Range(min: 120.0))->min);
    }

    /** A shop that already stores prices with tax types the number it stores. */
    #[Test]
    public function it_leaves_the_range_alone_when_prices_are_stored_with_tax(): void
    {
        $this->sellingWithTax(20.0);
        $this->option('woocommerce_prices_include_tax', 'yes');

        $this->assertSame(120.0, PriceTax::excluding(new Range(min: 120.0))->min);
    }

    private function sellingWithTax(float $percent): void
    {
        $this->option('woocommerce_calc_taxes', 'yes');
        $this->option('woocommerce_prices_include_tax', 'no');
        $this->option('woocommerce_tax_display_shop', 'incl');

        $this->rate = \WC_Tax::_insert_tax_rate([
            'tax_rate_country' => '',
            'tax_rate' => (string) $percent,
            'tax_rate_name' => 'Test',
            'tax_rate_priority' => 1,
            'tax_rate_compound' => 0,
            'tax_rate_shipping' => 0,
            'tax_rate_order' => 0,
            'tax_rate_class' => '',
        ]);

        \WC_Cache_Helper::invalidate_cache_group('taxes');
    }

    private function option(string $option, string $value): void
    {
        $this->options[$option] ??= (string) get_option($option, '');

        update_option($option, $value);
    }
}
