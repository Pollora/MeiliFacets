<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Range;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Listing\Range;

it('hands back the bound asked for and null for an open end', function (): void {
    $range = new Range(max: 40.0);

    expect($range->bound(PriceBound::Min))->toBeNull();
    expect($range->bound(PriceBound::Max))->toEqualWithDelta(40.0, PHP_FLOAT_EPSILON);
});

it('holds nothing until a bound is given', function (): void {
    expect(new Range()->isEmpty())->toBeTrue();
    expect(new Range(min: 0.0)->isEmpty())->toBeFalse();
    expect(new Range(max: 20.0)->isEmpty())->toBeFalse();
});

/** A facet can narrow the reachable prices under what the visitor asked for, and a range cannot draw a handle outside its own ends. */
it('brings a value back between its bounds', function (): void {
    $bounds = new Range(12.5, 26.0);

    expect(array_map($bounds->clamp(...), [5.0, 199.0, 20.0]))->toBe([12.5, 26.0, 20.0]);
});

it('lets an end it does not hold constrain nothing', function (): void {
    expect([
        new Range(min: 12.5)->clamp(199.0),
        new Range(min: 12.5)->clamp(5.0),
        new Range(max: 26.0)->clamp(5.0),
        new Range(max: 26.0)->clamp(199.0),
        new Range()->clamp(5.0),
    ])->toBe([199.0, 12.5, 5.0, 26.0, 5.0]);
});

it('gives a value its ratio between the bounds', function (): void {
    $bounds = new Range(12.5, 26.0);

    expect(array_map($bounds->ratio(...), [12.5, 26.0, 20.0, 199.0, 5.0]))->toBe([0.0, 1.0, 0.5556, 1.0, 0.0]);
});

it('puts everything at the start of a range with no span', function (): void {
    expect([
        new Range(26.0, 26.0)->ratio(26.0),
        new Range()->ratio(199.0),
        new Range(12.5, 26.0)->ratio(null),
    ])->toBe([0.0, 0.0, 0.0]);
});

it('formats a bound without an exponent', function (): void {
    expect([Range::formatBound(1e-9), Range::formatBound(1e21)])->toBe(['0', '1000000000000000000000']);
});

test('an inverted range contains nothing', function (): void {
    $inverted = new Range(50.0, 10.0);

    expect($inverted->contains(10.0))->toBeFalse();
    expect($inverted->contains(50.0))->toBeFalse();
    expect($inverted->contains(30.0))->toBeFalse();
});
