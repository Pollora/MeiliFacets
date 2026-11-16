<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\ApplyMode;

it('shows a button only when a submit is expected', function (): void {
    expect(ApplyMode::OnSubmit->needsButton())->toBeTrue();
    expect(ApplyMode::Immediate->needsButton())->toBeFalse();
});

it('names both modes for the markup to carry', function (): void {
    expect(ApplyMode::OnSubmit->value)->toBe('submit');
    expect(ApplyMode::Immediate->value)->toBe('immediate');
});
