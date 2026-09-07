<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Read here and by `tests/js/page-window.test.js`: the two copies answered differently until a shared table said so. */
final class PaginationTest extends TestCase
{
    /** Far above anything these cases count: the engine's own cap is a case of its own. */
    private const int UNBOUNDED = 1_000_000;

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('cases')]
    #[Test]
    public function it_answers_the_shared_cases(array $case): void
    {
        $pagination = new Pagination($case['current'], $case['perPage'], $case['total'], $case['reachable']);

        $this->assertSame(Pagination::SLOTS, $case['slotCount'], 'the shared cases expect another window width');
        $this->assertSame($case['pages'], $pagination->pages(), 'pages');
        $this->assertSame($case['resolvedCurrent'], $pagination->current, 'current');
        $this->assertSame($case['hasPages'], $pagination->hasPages(), 'hasPages');
        $this->assertSame($case['hasPrevious'], $pagination->hasPrevious(), 'hasPrevious');
        $this->assertSame($case['hasNext'], $pagination->hasNext(), 'hasNext');
        $this->assertSame($case['previous'], $pagination->previous(), 'previous');
        $this->assertSame($case['next'], $pagination->next(), 'next');
        $this->assertSame($case['slots'], $pagination->slots(), 'slots');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function cases(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__.'/../pagination-cases.json'), true);
        $sets = [];

        foreach ($cases as $case) {
            $sets[$case['case']] = [$case];
        }

        return $sets;
    }

    #[Test]
    public function it_offsets_a_page_by_the_ones_before_it(): void
    {
        $this->assertSame(0, (new Pagination(1, 16, 33, self::UNBOUNDED))->offset());
        $this->assertSame(16, (new Pagination(2, 16, 33, self::UNBOUNDED))->offset());
        $this->assertSame(32, (new Pagination(3, 16, 33, self::UNBOUNDED))->offset());
    }

    #[Test]
    public function it_holds_the_window_against_the_last_page(): void
    {
        $this->assertSame([307, 308, 309, 310, 311, 312, 313], (new Pagination(313, 16, 5000, self::UNBOUNDED))->slots());
    }
}
