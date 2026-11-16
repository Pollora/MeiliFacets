<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Support\ReservedParameters;
use Tests\TestCase;
use WooCommerce;

trait ReservedParametersHelpers
{
    private const string PROBE = 'meilifacets_probe';

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function whileActionCount(string $action, int $count, Closure $read): mixed
    {
        global $wp_actions;

        $actual = $wp_actions[$action] ?? 0;
        $wp_actions[$action] = $count;

        try {
            return $read();
        } finally {
            $wp_actions[$action] = $actual;
        }
    }
}

uses(TestCase::class, ReservedParametersHelpers::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('The names these tests expect are declared by WooCommerce.');
    }
});

it('adds the query vars plugins declare through the filter', function (): void {
    $names = new ReservedParameters()->wordPress();

    expect($names)->toContain('min_price');
    expect($names)->toContain('checkout-link');
});

it('lists each name once', function (): void {
    $names = new ReservedParameters()->wordPress();

    expect($names)->toBe(array_values(array_unique($names)));
});

it('names the price bounds for what woocommerce reads even once they are query vars', function (): void {
    $reserved = new ReservedParameters;

    expect($reserved->wordPress())->toContain('min_price');
    expect((string) $reserved->reason('min_price'))->toContain('$_GET');
});

it('takes the list of a parsed request as wordpress filtered it', function (): void {
    $probe = static fn (array $names): array => [...$names, self::PROBE];
    add_filter('query_vars', $probe);

    try {
        $names = $this->whileActionCount('parse_request', 1, new ReservedParameters()->wordPress(...));
    } finally {
        remove_filter('query_vars', $probe);
    }

    expect($names)->not->toContain(self::PROBE);
});

it('reads the list again once wordpress has initialised', function (): void {
    $reserved = new ReservedParameters;

    expect($this->whileActionCount('init', 0, $reserved->wordPress(...)))->not->toContain('checkout-link');
    expect($reserved->wordPress())->toContain('checkout-link');
});
