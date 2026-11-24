<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\WooCommerceDefaultSort;
use Tests\TestCase;

trait WooCommerceDefaultSortSupport
{
    private const string SETTING = 'woocommerce_default_catalog_orderby';

    /** @var list<callable(): string> */
    private array $answers = [];

    /**
     * @return list<string>
     */
    private function defaultSortFor(string $setting): array
    {
        $this->answer(static fn (): string => $setting);

        return new WooCommerceDefaultSort()->expressions();
    }

    /**
     * @param  callable(): string  $answer
     */
    private function answer(callable $answer): void
    {
        $this->answers[] = $answer;
        add_filter(self::SETTING, $answer);
    }
}

/** The setting is answered by a filter: the saved option is never written. */
uses(TestCase::class, WooCommerceDefaultSortSupport::class);

afterEach(function (): void {
    array_walk($this->answers, static fn (callable $answer): bool => remove_filter(self::SETTING, $answer));
});

it('sorts as woocommerce sorts by default', function (string $setting, array $expressions): void {
    expect($this->defaultSortFor($setting))->toBe($expressions);
})->with([
    'custom ordering, then name' => ['menu_order', ['menu_order:asc', 'post_title:asc']],
    'name' => ['title', ['post_title:asc']],
    'latest' => ['date', ['post_date:desc']],
    'oldest' => ['date-asc', ['post_date:asc']],
    'price, low to high' => ['price', ['price.min:asc']],
    'price, high to low' => ['price-desc', ['price.max:desc']],
    'relevance' => ['relevance', []],
    'popularity' => ['popularity', ['total_sales:desc', 'ID:desc']],
    'rating' => ['rating', ['average_rating:desc', 'rating_count:desc', 'ID:desc']],
]);

test('a sorting the listing cannot follow keeps the engine order', function (): void {
    expect($this->defaultSortFor('rand'))->toBe([]);
});

test('the customizer offers only the sortings the listing follows', function (): void {
    $choices = array_fill_keys(['menu_order', 'popularity', 'rating', 'rand', 'date', 'price', 'price-desc'], '');

    $offered = apply_filters('woocommerce_default_catalog_orderby_options', $choices);

    expect(array_keys($offered))->toBe(['menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc']);
});

test('the setting is read once', function (): void {
    $reads = 0;
    $this->answer(static function () use (&$reads): string {
        $reads++;

        return 'date';
    });
    $defaultSort = new WooCommerceDefaultSort;

    $defaultSort->expressions();
    $defaultSort->expressions();

    expect($reads)->toBe(1);
});
