<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\ApplyShape;
use Modules\MeiliFacets\Enums\ResetShape;
use ValueError;

it('reads a variant written plainly or bound', function (): void {
    expect(ApplyShape::fromAttribute('block'))->toBe(ApplyShape::Block);
    expect(ResetShape::fromAttribute(ResetShape::Icon))->toBe(ResetShape::Icon);
});

it('refuses a variant the enum does not declare', function (): void {
    $this->expectException(ValueError::class);

    ApplyShape::fromAttribute('round');
});
