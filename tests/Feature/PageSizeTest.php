<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Listing\PageSize;
use Tests\TestCase;

trait PageSizeHelpers
{
    private const string PER_PAGE = 'loop_shop_per_page';

    private const string COLUMNS = 'pre_option_woocommerce_catalog_columns';

    private const string ROWS = 'pre_option_woocommerce_catalog_rows';

    /** @var list<array{string, Closure}> */
    private array $answers = [];

    private function answer(string $hook, Closure $answer): void
    {
        $this->answers[] = [$hook, $answer];
        add_filter($hook, $answer);
    }
}

/** Every setting is answered by a filter: no option is ever written. */
uses(TestCase::class, PageSizeHelpers::class);

afterEach(function (): void {
    foreach ($this->answers as [$hook, $answer]) {
        remove_filter($hook, $answer);
    }
});

it('shows as many products as the catalog grid holds', function (): void {
    $this->answer(self::COLUMNS, static fn (): int => 3);
    $this->answer(self::ROWS, static fn (): int => 5);

    expect(PageSize::forProducts())->toBe(15);
});

it('lets a theme choose its own page size', function (): void {
    $this->answer(self::COLUMNS, static fn (): int => 3);
    $this->answer(self::ROWS, static fn (): int => 5);
    $this->answer(self::PER_PAGE, static fn (int $default): int => $default === 15 ? 24 : 0);

    expect(PageSize::forProducts())->toBe(24);
});

it('reads a page size answered as text as a number', function (): void {
    $this->answer(self::PER_PAGE, static fn (): string => '30');

    expect(PageSize::forProducts())->toBe(30);
});
