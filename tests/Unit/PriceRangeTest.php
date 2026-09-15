<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\FilterExpression;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceRangeTest extends TestCase
{
    #[Test]
    public function it_holds_nothing_until_a_bound_is_given(): void
    {
        $this->assertTrue(new Range()->isEmpty());
        $this->assertFalse(new Range(min: 0.0)->isEmpty());
        $this->assertFalse(new Range(max: 20.0)->isEmpty());
    }

    /**
     * Two intervals overlap unless one ends before the other starts. Writing it as
     * `min >= asked AND max <= asked` would keep only products that fit inside the
     * range, losing every one that merely reaches into it.
     */
    #[Test]
    public function it_asks_the_engine_for_an_overlap(): void
    {
        $this->assertSame(
            'price.min <= 70 AND price.max >= 40',
            FilterExpression::overlapping(new Range(40.0, 70.0))
        );
    }

    #[Test]
    public function it_leaves_an_open_end_unconstrained(): void
    {
        $this->assertSame('price.max >= 40', FilterExpression::overlapping(new Range(min: 40.0)));
        $this->assertSame('price.min <= 70', FilterExpression::overlapping(new Range(max: 70.0)));
        $this->assertSame('', FilterExpression::overlapping(new Range));
    }

    /**
     * Fixed notation to four decimals: `(string) 1.0E-9` is not a filter, and no
     * currency carries more than three. A bound is written as asked, not rounded
     * to the money format, so a hand-typed `99.999` still means what it says.
     */
    #[Test]
    public function it_writes_a_bound_the_engine_can_read(): void
    {
        $this->assertSame('price.min <= 99.999', FilterExpression::overlapping(new Range(max: 99.999)));
        $this->assertSame('price.min <= 0', FilterExpression::overlapping(new Range(max: 0.0)));
        $this->assertSame('price.min <= 12.5', FilterExpression::overlapping(new Range(max: 12.50)));
        $this->assertSame('price.min <= 1000000', FilterExpression::overlapping(new Range(max: 1e6)));
    }

    #[Test]
    public function it_reads_its_bounds_off_the_engine_statistics(): void
    {
        $bounds = new PriceFilter('Price')->boundsFrom([
            'price.min' => ['min' => 4.5, 'max' => 190.0],
            'price.max' => ['min' => 4.5, 'max' => 199.0],
        ]);

        $this->assertEqualsWithDelta(4.5, $bounds->min, PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(199.0, $bounds->max, PHP_FLOAT_EPSILON);
    }

    /** A listing whose engine reported nothing must not draw a range from nowhere. */
    #[Test]
    public function it_has_no_bounds_when_the_engine_reported_none(): void
    {
        $this->assertTrue(new PriceFilter('Price')->boundsFrom([])->isEmpty());
    }

    /**
     * A facet can narrow the reachable prices under what the visitor asked for, and
     * a range cannot draw a handle outside its own ends.
     */
    #[Test]
    public function it_brings_a_value_back_between_its_bounds(): void
    {
        $bounds = new Range(12.5, 26.0);

        $this->assertSame(
            [12.5, 26.0, 20.0],
            array_map($bounds->clamp(...), [5.0, 199.0, 20.0])
        );
    }

    #[Test]
    public function it_lets_an_end_it_does_not_hold_constrain_nothing(): void
    {
        $this->assertSame([199.0, 5.0, 5.0], [
            new Range(min: 12.5)->clamp(199.0),
            new Range(max: 26.0)->clamp(5.0),
            new Range()->clamp(5.0),
        ]);
    }

    #[Test]
    public function it_gives_a_value_its_ratio_between_the_bounds(): void
    {
        $bounds = new Range(12.5, 26.0);

        $this->assertSame(
            [0.0, 1.0, 0.5556, 1.0, 0.0],
            array_map($bounds->ratio(...), [12.5, 26.0, 20.0, 199.0, 5.0])
        );
    }

    #[Test]
    public function it_puts_everything_at_the_start_of_a_range_with_no_span(): void
    {
        $this->assertSame([0.0, 0.0, 0.0], [
            new Range(26.0, 26.0)->ratio(26.0),
            new Range()->ratio(199.0),
            new Range(12.5, 26.0)->ratio(null),
        ]);
    }

    /** A legend cannot sit on a flex row, so the control names itself. */
    /** One end alone draws nothing, and leaves the other reading `aria-valuemax=""`. */
    #[Test]
    public function it_has_no_bounds_when_the_engine_reported_only_one_end(): void
    {
        $filter = new PriceFilter('Price');

        $this->assertTrue($filter->boundsFrom(['price.min' => ['min' => 9.5]])->isEmpty());
        $this->assertTrue($filter->boundsFrom(['price.max' => ['max' => 46.0]])->isEmpty());
    }

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
