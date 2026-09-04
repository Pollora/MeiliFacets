<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\ApplyMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplyModeTest extends TestCase
{
    #[Test]
    public function it_shows_a_button_only_when_a_submit_is_expected(): void
    {
        $this->assertTrue(ApplyMode::OnSubmit->needsButton());
        $this->assertFalse(ApplyMode::Immediate->needsButton());
    }

    #[Test]
    public function it_names_both_modes_for_the_markup_to_carry(): void
    {
        $this->assertSame('submit', ApplyMode::OnSubmit->value);
        $this->assertSame('immediate', ApplyMode::Immediate->value);
    }
}
