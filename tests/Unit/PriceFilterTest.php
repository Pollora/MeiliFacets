<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\PriceFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceFilterTest extends TestCase
{
    #[Test]
    public function it_reads_its_bounds_off_the_engine_statistics(): void
    {
        $bounds = new PriceFilter('Price')->boundsFrom([
            'price.min' => ['min' => 4.5, 'max' => 190.0],
            'price.max' => ['min' => 4.5, 'max' => 199.0],
        ]);

        $this->assertEqualsWithDelta(4.0, $bounds->min, PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(199.0, $bounds->max, PHP_FLOAT_EPSILON);
    }

    /** A handle only rests on whole units: an end left at 46.40 would put the product priced there out of reach. */
    #[Test]
    public function it_widens_its_bounds_to_whole_units_as_woocommerce_does(): void
    {
        $bounds = new PriceFilter('Price')->boundsFrom([
            'price.min' => ['min' => 9.8, 'max' => 40.0],
            'price.max' => ['min' => 12.0, 'max' => 46.4],
        ]);

        $this->assertEqualsWithDelta(9.0, $bounds->min, PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(47.0, $bounds->max, PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function it_draws_no_range_over_a_single_price(): void
    {
        $filter = new PriceFilter('Price');

        $this->assertTrue($filter->boundsFrom(['price.min' => ['min' => 20.0], 'price.max' => ['max' => 20.0]])->isEmpty());
        $this->assertFalse($filter->boundsFrom(['price.min' => ['min' => 20.0], 'price.max' => ['max' => 20.4]])->isEmpty());
    }

    /** A listing whose engine reported nothing must not draw a range from nowhere. */
    #[Test]
    public function it_has_no_bounds_when_the_engine_reported_none(): void
    {
        $this->assertTrue(new PriceFilter('Price')->boundsFrom([])->isEmpty());
    }

    /** One end alone draws nothing, and leaves the other reading `aria-valuemax=""`. */
    #[Test]
    public function it_has_no_bounds_when_the_engine_reported_only_one_end(): void
    {
        $filter = new PriceFilter('Price');

        $this->assertTrue($filter->boundsFrom(['price.min' => ['min' => 9.5]])->isEmpty());
        $this->assertTrue($filter->boundsFrom(['price.max' => ['max' => 46.0]])->isEmpty());
    }

    /** A legend cannot sit on a flex row, so the control names itself. */
    #[Test]
    public function it_names_itself_only_when_it_shows_a_range(): void
    {
        $this->assertTrue(new PriceFilter('Price', [PricePart::Slider])->namesItself());
        $this->assertFalse(new PriceFilter('Price', [PricePart::Fields])->namesItself());
    }

    #[Test]
    public function it_answers_to_the_name_a_project_gives_it(): void
    {
        $this->assertSame('price', new PriceFilter('Price')->name);
        $this->assertSame('tarif', new PriceFilter('Price', name: 'tarif')->name);
    }

    #[Test]
    public function it_maps_each_bound_to_the_parameter_and_hook_that_carry_it(): void
    {
        $this->assertSame('min_price', PriceBound::Min->parameter()->value);
        $this->assertSame('max_price', PriceBound::Max->parameter()->value);
        $this->assertSame('price-min', PriceBound::Min->hook()->value);
        $this->assertSame('price-max', PriceBound::Max->hook()->value);
    }
}
