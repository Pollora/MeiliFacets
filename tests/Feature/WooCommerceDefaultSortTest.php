<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Listing\CatalogOrderUnsupported;
use Modules\MeiliFacets\Listing\WooCommerceDefaultSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The setting is answered by a filter: the saved option is never written. */
final class WooCommerceDefaultSortTest extends TestCase
{
    private const string SETTING = 'woocommerce_default_catalog_orderby';

    /** @var list<callable(): string> */
    private array $answers = [];

    protected function tearDown(): void
    {
        array_walk($this->answers, static fn (callable $answer): bool => remove_filter(self::SETTING, $answer));

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function reproducibleSortings(): iterable
    {
        yield 'custom ordering, then name' => ['menu_order', ['menu_order:asc', 'post_title:asc']];
        yield 'name' => ['title', ['post_title:asc']];
        yield 'latest' => ['date', ['post_date:desc']];
        yield 'oldest' => ['date-asc', ['post_date:asc']];
        yield 'price, low to high' => ['price', ['price.min:asc']];
        yield 'price, high to low' => ['price-desc', ['price.max:desc']];
        yield 'relevance' => ['relevance', []];
    }

    /**
     * @param  list<string>  $expressions
     */
    #[DataProvider('reproducibleSortings')]
    #[Test]
    public function it_sorts_as_woocommerce_sorts_by_default(string $setting, array $expressions): void
    {
        $this->assertSame($expressions, $this->defaultSortFor($setting));
    }

    #[Test]
    public function a_sorting_the_engine_cannot_reproduce_keeps_its_order_and_says_why(): void
    {
        Exceptions::fake();

        $this->assertSame([], $this->defaultSortFor('popularity'));

        Exceptions::assertReported(
            fn (CatalogOrderUnsupported $unsupported): bool => str_contains($unsupported->getMessage(), '"popularity"')
        );
    }

    #[Test]
    public function the_setting_is_read_once(): void
    {
        $reads = 0;
        $this->answer(static function () use (&$reads): string {
            $reads++;

            return 'date';
        });
        $defaultSort = new WooCommerceDefaultSort;

        $defaultSort->expressions();
        $defaultSort->expressions();

        $this->assertSame(1, $reads);
    }

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
