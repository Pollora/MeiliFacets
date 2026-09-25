<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\Badge;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BadgeTest extends TestCase
{
    /** The control is described by its badge, hidden or not: a zero left in it would still be read out. */
    #[Test]
    public function it_says_nothing_at_zero(): void
    {
        $badge = new Badge('count', 0);

        $this->assertTrue($badge->holdsNothing());
        $this->assertSame('', $badge->text());
    }

    #[Test]
    public function it_shows_the_count_it_holds(): void
    {
        $badge = new Badge('count', 3);

        $this->assertFalse($badge->holdsNothing());
        $this->assertSame('3', $badge->text());
    }
}
