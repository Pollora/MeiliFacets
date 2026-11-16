<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\UniqueList;

use Modules\MeiliFacets\Support\UniqueList;

it('keeps each value where it first appears', function (): void {
    expect(UniqueList::merge(['title', 'excerpt'], ['content', 'title']))->toBe(['title', 'excerpt', 'content']);
});

it('drops a repeat inside a single list', function (): void {
    expect(UniqueList::merge(['red', 'blue', 'red']))->toBe(['red', 'blue']);
});

it('hands back a list with no gap in its keys', function (): void {
    $merged = UniqueList::merge(['a', 'a'], ['b']);

    expect(array_is_list($merged))->toBeTrue();
});

it('keeps values that differ only by case', function (): void {
    expect(UniqueList::merge(['Red'], ['red']))->toBe(['Red', 'red']);
});

it('merges nothing into an empty list', function (): void {
    expect(UniqueList::merge())->toBe([]);
    expect(UniqueList::merge([], []))->toBe([]);
});
