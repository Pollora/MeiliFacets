<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Range;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RangeTest extends TestCase
{
    #[Test]
    public function it_holds_nothing_until_a_bound_is_given(): void
    {
        $this->assertTrue(new Range()->isEmpty());
        $this->assertFalse(new Range(min: 0.0)->isEmpty());
        $this->assertFalse(new Range(max: 20.0)->isEmpty());
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
        $this->assertSame([199.0, 12.5, 5.0, 26.0, 5.0], [
            new Range(min: 12.5)->clamp(199.0),
            new Range(min: 12.5)->clamp(5.0),
            new Range(max: 26.0)->clamp(5.0),
            new Range(max: 26.0)->clamp(199.0),
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

    #[Test]
    public function it_formats_a_bound_without_an_exponent(): void
    {
        $this->assertSame(['0', '1000000000000000000000'], [Range::formatBound(1e-9), Range::formatBound(1e21)]);
    }
}
