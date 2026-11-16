<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\CatalogOrderSetting;

use Modules\MeiliFacets\Listing\CatalogOrderSetting;

it('follows the setting as woocommerce reads it', function (string $setting, array $expressions): void {
    $order = CatalogOrderSetting::of($setting);

    expect($order->listingCanFollow())->toBeTrue();
    expect($order->expressions())->toBe($expressions);
})->with(function () {
    yield 'no direction takes the default one' => ['date', ['post_date:desc']];
    yield 'explicit direction' => ['date-asc', ['post_date:asc']];
    yield 'price going down' => ['price-desc', ['price.max:desc']];
    yield 'written in capitals' => ['PRICE-DESC', ['price.max:desc']];
    yield 'unknown direction takes the default one' => ['date-sideways', ['post_date:desc']];
    yield 'empty direction takes the default one' => ['price-', ['price.min:asc']];
    yield 'anything after the direction is ignored' => ['price-desc-extra', ['price.max:desc']];
    yield 'relevance keeps the engine order' => ['relevance', []];
});

it('refuses a setting it cannot follow and adds no sort', function (string $setting): void {
    $order = CatalogOrderSetting::of($setting);

    expect($order->listingCanFollow())->toBeFalse();
    expect($order->expressions())->toBe([]);
})->with(function () {
    yield 'popularity' => ['popularity'];
    yield 'rating' => ['rating'];
    yield 'empty' => [''];
    yield 'direction alone' => ['-desc'];
    yield 'a known order glued to its direction' => ['pricedesc'];
});
