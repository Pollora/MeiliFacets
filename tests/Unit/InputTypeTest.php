<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\InputType;
use Modules\MeiliFacets\Enums\SelectionMode;

it('ticks several values with checkboxes', function (): void {
    expect(InputType::forSelection(SelectionMode::Multiple)->value)->toBe('checkbox');
});

it('picks a single value with radios', function (): void {
    expect(InputType::forSelection(SelectionMode::Single)->value)->toBe('radio');
});
