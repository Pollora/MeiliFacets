<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\SortCaption;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SortCaptionTest extends TestCase
{
    /** A sentence with no slot for the order in force is read whole: nothing to swap once the visitor picks another. */
    #[Test]
    public function it_reads_a_sentence_without_a_choice_as_its_lead_alone(): void
    {
        $caption = SortCaption::withoutChoice('Order');

        $this->assertFalse($caption->holdsChoice());
        $this->assertSame('Order', $caption->label);
        $this->assertSame('Order', $caption->lead);
        $this->assertNull($caption->choice);
        $this->assertSame('', $caption->trail);
    }

    #[Test]
    public function it_holds_the_choice_it_was_given(): void
    {
        $this->assertTrue(new SortCaption('Sort by', 'Sort by: ', 'Relevance', '')->holdsChoice());
    }

    /** An empty choice is still a slot the client writes the order into. */
    #[Test]
    public function it_holds_an_empty_choice_too(): void
    {
        $this->assertTrue(new SortCaption('Sort by', 'Sort by: ', '', '')->holdsChoice());
    }
}
