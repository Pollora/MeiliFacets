<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Pagination;

// Read here and by `tests/ts/page-window.test.ts`: the two copies answered differently until a shared table said so.

// Far above anything these cases count: the engine's own cap is a case of its own.
const PAGINATION_UNBOUNDED = 1_000_000;

/**
 * @return array<string, array{array<string, mixed>}>
 */
dataset('pagination cases', function (): array {
    $cases = json_decode((string) file_get_contents(__DIR__.'/../pagination-cases.json'), true);
    $sets = [];

    foreach ($cases as $case) {
        $sets[$case['case']] = [$case];
    }

    return $sets;
});

/**
 * @param  array<string, mixed>  $case
 */
it('answers the shared cases', function (array $case): void {
    $pagination = new Pagination($case['asked'], $case['perPage'], $case['total'], $case['reachable']);

    $this->assertSame(Pagination::SLOTS, $case['slotCount'], 'the shared cases expect another window width');
    $this->assertSame($case['pages'], $pagination->pages(), 'pages');
    $this->assertSame($case['current'], $pagination->current, 'current');
    $this->assertSame($case['isPastTheEnd'], $pagination->isPastTheEnd(), 'isPastTheEnd');
    $this->assertSame($case['hasPages'], $pagination->hasPages(), 'hasPages');
    $this->assertSame($case['hasPrevious'], $pagination->hasPrevious(), 'hasPrevious');
    $this->assertSame($case['hasNext'], $pagination->hasNext(), 'hasNext');
    $this->assertSame($case['previous'], $pagination->previous(), 'previous');
    $this->assertSame($case['next'], $pagination->next(), 'next');
    $this->assertSame($case['slots'], $pagination->slots(), 'slots');
})->with('pagination cases');

// The message must tell which of the two truths it is: nothing at all, or nothing here.
it('tells a page past the end from an empty result', function (): void {
    expect(new Pagination(999, 10, 1570, PAGINATION_UNBOUNDED)->isPastTheEnd())->toBeTrue();
    expect(new Pagination(1, 10, 1570, PAGINATION_UNBOUNDED)->isPastTheEnd())->toBeFalse();
    expect(new Pagination(999, 10, 0, PAGINATION_UNBOUNDED)->isPastTheEnd())->toBeFalse();
});

// The page that was asked for stays readable, next to the one that exists.
it('remembers the page it was asked for', function (): void {
    $pagination = new Pagination(999, 10, 1570, PAGINATION_UNBOUNDED);

    expect($pagination->asked)->toBe(999);
    expect($pagination->current)->toBe(157);
});

it('offsets a page by the ones before it', function (): void {
    expect(new Pagination(1, 16, 33, PAGINATION_UNBOUNDED)->offset())->toBe(0);
    expect(new Pagination(2, 16, 33, PAGINATION_UNBOUNDED)->offset())->toBe(16);
    expect(new Pagination(3, 16, 33, PAGINATION_UNBOUNDED)->offset())->toBe(32);
});

it('holds the window against the last page', function (): void {
    expect(new Pagination(313, 16, 5000, PAGINATION_UNBOUNDED)->slots())->toBe([307, 308, 309, 310, 311, 312, 313]);
});
