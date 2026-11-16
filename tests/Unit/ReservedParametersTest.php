<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\UrlParameters;

it('refuses a name a plugin reads from the query string', function (): void {
    $reserved = new ReservedParameters;

    foreach (['min_price', 'max_price', 'rating_filter', 'orderby'] as $parameter) {
        $this->assertNotNull($reserved->reason($parameter), $parameter.' is taken and went unnoticed.');
    }
});

it('refuses every name under woocommerce filter prefix', function (): void {
    expect(new ReservedParameters()->reason('filter_size'))->not->toBeNull();
});

it('leaves a name nothing claims alone', function (): void {
    expect(new ReservedParameters()->reason('price_min'))->toBeNull();
});

it('accepts the price bounds while they keep their default names', function (): void {
    $accepted = new ReservedParameters()->acceptedFor(new UrlParameters([]));

    expect($accepted)->toBe(['min_price', 'max_price']);
});

it('stops accepting a bound the project renamed', function (): void {
    $accepted = new ReservedParameters()->acceptedFor(new UrlParameters([], ['min_price' => 'prix_min']));

    expect($accepted)->toBe(['max_price']);
});

it('names every offender it was handed', function (): void {
    $conflicts = new ReservedParameters()->conflicts(['categorie', 'min_price', 'utm_source']);

    expect(array_keys($conflicts))->toBe(['min_price', 'utm_source']);
});
