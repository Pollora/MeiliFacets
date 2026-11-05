<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\Badge;
use Modules\MeiliFacets\View\Disclosure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DisclosureTest extends TestCase
{
    #[Test]
    public function it_reads_its_label_aloud_by_default(): void
    {
        $this->assertTrue(new Disclosure('Brand', 'panel-brand', new Badge('count', 2))->readsLabelAloud());
    }

    #[Test]
    public function it_keeps_a_silent_label_shown_but_not_read_and_carries_no_badge(): void
    {
        $disclosure = Disclosure::withSilentLabel('Sort by', 'panel-sort');

        $this->assertFalse($disclosure->readsLabelAloud());
        $this->assertSame('Sort by', $disclosure->label);
        $this->assertSame('panel-sort', $disclosure->panelId);
        $this->assertNull($disclosure->badge);
    }
}
