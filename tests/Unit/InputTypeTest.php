<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\InputType;
use Modules\MeiliFacets\Enums\SelectionMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InputTypeTest extends TestCase
{
    #[Test]
    public function it_ticks_several_values_with_checkboxes(): void
    {
        $this->assertSame('checkbox', InputType::forSelection(SelectionMode::Multiple)->value);
    }

    #[Test]
    public function it_picks_a_single_value_with_radios(): void
    {
        $this->assertSame('radio', InputType::forSelection(SelectionMode::Single)->value);
    }
}
