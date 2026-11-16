<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\SortCaption;

// A sentence with no slot for the order in force is read whole: nothing to swap once the visitor picks another.
it('reads a sentence without a choice as its lead alone', function (): void {
    $caption = SortCaption::withoutChoice('Order');

    expect($caption->holdsChoice())->toBeFalse();
    expect($caption->label)->toBe('Order');
    expect($caption->lead)->toBe('Order');
    expect($caption->choice)->toBeNull();
    expect($caption->trail)->toBe('');
});

it('holds the choice it was given', function (): void {
    expect(new SortCaption('Sort by', 'Sort by: ', 'Relevance', '')->holdsChoice())->toBeTrue();
});

// An empty choice is still a slot the client writes the order into.
it('holds an empty choice too', function (): void {
    expect(new SortCaption('Sort by', 'Sort by: ', '', '')->holdsChoice())->toBeTrue();
});
