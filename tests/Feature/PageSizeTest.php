<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Listing\PageSize;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Every setting is answered by a filter: no option is ever written. */
final class PageSizeTest extends TestCase
{
    private const string PER_PAGE = 'loop_shop_per_page';

    private const string COLUMNS = 'pre_option_woocommerce_catalog_columns';

    private const string ROWS = 'pre_option_woocommerce_catalog_rows';

    /** @var list<array{string, Closure}> */
    private array $answers = [];

    protected function tearDown(): void
    {
        foreach ($this->answers as [$hook, $answer]) {
            remove_filter($hook, $answer);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_shows_as_many_products_as_the_catalog_grid_holds(): void
    {
        $this->answer(self::COLUMNS, static fn (): int => 3);
        $this->answer(self::ROWS, static fn (): int => 5);

        $this->assertSame(15, PageSize::forProducts());
    }

    #[Test]
    public function it_lets_a_theme_choose_its_own_page_size(): void
    {
        $this->answer(self::COLUMNS, static fn (): int => 3);
        $this->answer(self::ROWS, static fn (): int => 5);
        $this->answer(self::PER_PAGE, static fn (int $default): int => $default === 15 ? 24 : 0);

        $this->assertSame(24, PageSize::forProducts());
    }

    #[Test]
    public function it_reads_a_page_size_answered_as_text_as_a_number(): void
    {
        $this->answer(self::PER_PAGE, static fn (): string => '30');

        $this->assertSame(30, PageSize::forProducts());
    }

    private function answer(string $hook, Closure $answer): void
    {
        $this->answers[] = [$hook, $answer];
        add_filter($hook, $answer);
    }
}
