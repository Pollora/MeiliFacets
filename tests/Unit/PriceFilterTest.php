<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\PriceFilter;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\PriceFilter;

it('reads its bounds off the engine statistics', function (): void {
    $bounds = new PriceFilter('Price')->boundsFrom([
        'price.min' => ['min' => 4.5, 'max' => 190.0],
        'price.max' => ['min' => 4.5, 'max' => 199.0],
    ]);

    expect($bounds->min)->toEqualWithDelta(4.0, PHP_FLOAT_EPSILON);
    expect($bounds->max)->toEqualWithDelta(199.0, PHP_FLOAT_EPSILON);
});

/** A handle only rests on whole units: an end left at 46.40 would put the product priced there out of reach. */
it('widens its bounds to whole units as woocommerce does', function (): void {
    $bounds = new PriceFilter('Price')->boundsFrom([
        'price.min' => ['min' => 9.8, 'max' => 40.0],
        'price.max' => ['min' => 12.0, 'max' => 46.4],
    ]);

    expect($bounds->min)->toEqualWithDelta(9.0, PHP_FLOAT_EPSILON);
    expect($bounds->max)->toEqualWithDelta(47.0, PHP_FLOAT_EPSILON);
});

it('draws no range over a single price', function (): void {
    $filter = new PriceFilter('Price');

    expect($filter->boundsFrom(['price.min' => ['min' => 20.0], 'price.max' => ['max' => 20.0]])->isEmpty())->toBeTrue();
    expect($filter->boundsFrom(['price.min' => ['min' => 20.0], 'price.max' => ['max' => 20.4]])->isEmpty())->toBeFalse();
});

/** A listing whose engine reported nothing must not draw a range from nowhere. */
it('has no bounds when the engine reported none', function (): void {
    expect(new PriceFilter('Price')->boundsFrom([])->isEmpty())->toBeTrue();
});

/** One end alone draws nothing, and leaves the other reading `aria-valuemax=""`. */
it('has no bounds when the engine reported only one end', function (): void {
    $filter = new PriceFilter('Price');

    expect($filter->boundsFrom(['price.min' => ['min' => 9.5]])->isEmpty())->toBeTrue();
    expect($filter->boundsFrom(['price.max' => ['max' => 46.0]])->isEmpty())->toBeTrue();
});

/** A legend cannot sit on a flex row, so the control names itself. */
it('names itself only when it shows a range', function (): void {
    expect(new PriceFilter('Price', [PricePart::Slider])->namesItself())->toBeTrue();
    expect(new PriceFilter('Price', [PricePart::Fields])->namesItself())->toBeFalse();
});

it('answers to the name a project gives it', function (): void {
    expect(new PriceFilter('Price')->name)->toBe('price');
    expect(new PriceFilter('Price', name: 'tarif')->name)->toBe('tarif');
});

it('maps each bound to the parameter and hook that carry it', function (): void {
    expect(PriceBound::Min->parameter()->value)->toBe('min_price');
    expect(PriceBound::Max->parameter()->value)->toBe('max_price');
    expect(PriceBound::Min->hook()->value)->toBe('price-min');
    expect(PriceBound::Max->hook()->value)->toBe('price-max');
});
