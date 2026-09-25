<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\SortSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The label and the value arrive as two patterns: nothing is cut out of a translated sentence. */
final class SortSummaryTest extends TestCase
{
    #[Test]
    public function it_writes_what_the_value_pattern_puts_around_the_order_in_force(): void
    {
        $summary = SortSummary::of('Trier par', "\u{A0}: :choice", 'Prix croissant');

        $this->assertSame('Trier par', $summary->label);
        $this->assertSame("\u{A0}: ", $summary->lead);
        $this->assertSame('Prix croissant', $summary->choice);
        $this->assertSame('', $summary->trail);
    }

    #[Test]
    public function it_keeps_what_a_language_writes_after_the_order(): void
    {
        $summary = SortSummary::of('Sort', ' (:choice)', 'Price');

        $this->assertSame(' (', $summary->lead);
        $this->assertSame(')', $summary->trail);
    }
}
