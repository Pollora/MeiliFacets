<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\PriceRangeShown;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\ActiveValuePatterns;
use Modules\MeiliFacets\View\Components\Listing\Price;
use Modules\MeiliFacets\View\RangeHandle;

beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    WordPress::fake('get_query_var', '');
    // Markup and entities, as `wc_price()` hands them back.
    WordPress::fake('wc_price', static fn (float $value): string => ' <span>'.number_format($value, 2).'&nbsp;&euro;</span> ');
    WordPress::fake('wp_strip_all_tags', strip_tags(...));
    $container = new Container;
    $container->instance('view', new class
    {
        public function getConsumableComponentData(string $key): mixed
        {
            return null;
        }
    });
    Facade::setFacadeApplication($container);
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

/**
 * @param  array<string, array<string, float>>  $stats
 * @param  array<string, string>  $query
 */
function priceComponent(array $stats, array $query = []): Price
{
    WordPress::$query = $query;
    $registry = new ListingRegistry(new Container);
    $registry->add(new FakeListing([new PriceFilter('Price', [PricePart::Fields])]));
    $parameters = new UrlParameters;

    return new Price(new CurrentListing(
        $registry,
        new ListingSearch(new FakeSearchEngine(['results' => ['totalHits' => 1, 'facetStats' => $stats], 'price' => ['facetStats' => $stats]]), new DisjunctiveFacetCounter),
        new StateReader($parameters),
        new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
        $parameters,
        new EngineLimits(1000),
    ), new Money, $parameters, 'price');
}

const FROM_10_TO_100 = ['price.min' => ['min' => 10.0, 'max' => 10.0], 'price.max' => ['min' => 100.0, 'max' => 100.0]];

it('bounds each handle by the other one, between the reachable prices', function (): void {
    $component = priceComponent(FROM_10_TO_100, ['min_price' => '20', 'max_price' => '60']);

    [$low, $high] = $component->handles();
    $reach = static fn (RangeHandle $handle): array => [$handle->value, $handle->shown, $handle->floor, $handle->ceiling, $handle->at];

    expect($reach($low))->toBe([20.0, 20.0, 10.0, 60.0, 0.1111])
        ->and($reach($high))->toBe([60.0, 60.0, 20.0, 100.0, 0.5556])
        ->and([$component->fill()->from, $component->fill()->to])->toBe([0.1111, 0.5556])
        ->and($component->readout())->toBe("20.00\u{a0}€ – 60.00\u{a0}€");
});

it('draws both handles at zero, showing no number, when the catalogue offers no price', function (): void {
    [$low, $high] = priceComponent([])->handles();

    foreach ([$low, $high] as $handle) {
        expect([$handle->value, $handle->shown, $handle->floor, $handle->ceiling, $handle->at])
            ->toBe([0.0, null, 0.0, 0.0, 0.0]);
    }
});

it('describes the shop\'s price format with its entities as characters', function (): void {
    WordPress::fake('get_woocommerce_price_format', '%2$s&nbsp;%1$s');
    WordPress::fake('get_woocommerce_currency_symbol', '&euro;');
    WordPress::fake('wc_get_price_decimals', 2);
    WordPress::fake('wc_get_price_decimal_separator', ',');
    WordPress::fake('wc_get_price_thousand_separator', ' ');

    expect(new Money()->describe())->toBe([
        'format' => "%2\$s\u{a0}%1\$s",
        'symbol' => '€',
        'decimals' => 2,
        'decimal' => ',',
        'thousand' => ' ',
    ])->and(new Money()->of(null))->toBe('');
});

it('words a price range after the bounds it has', function (?float $min, ?float $max, string $words): void {
    expect(ActiveValuePatterns::translated()->range(new Range($min, $max), 'A', 'B'))->toBe($words);
})->with([
    'both' => [1.0, 2.0, 'A – B'],
    'a minimum only' => [1.0, null, 'From A'],
    'a maximum only' => [null, 2.0, 'Up to B'],
]);
