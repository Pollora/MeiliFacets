<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\Badge;

// The control is described by its badge, hidden or not: a zero left in it would still be read out.
it('says nothing at zero', function (): void {
    $badge = new Badge('count', 0);

    expect($badge->holdsNothing())->toBeTrue();
    expect($badge->text())->toBe('');
});

it('shows the count it holds', function (): void {
    $badge = new Badge('count', 3);

    expect($badge->holdsNothing())->toBeFalse();
    expect($badge->text())->toBe('3');
});
