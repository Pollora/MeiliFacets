<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\HtmlString;
use Modules\MeiliFacets\Enums\Contract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bringing the listing back into view is asked for on the component, one at a
 * time. A theme that says nothing gets a page that holds still.
 */
final class ScrollOptionTest extends TestCase
{
    #[Test]
    public function it_marks_nothing_by_default(): void
    {
        $this->assertStringNotContainsString(Contract::SCROLL_ATTRIBUTE, $this->render(false));
    }

    #[Test]
    public function it_marks_the_component_that_asks(): void
    {
        $this->assertStringContainsString(Contract::SCROLL_ATTRIBUTE, $this->render(true));
    }

    private function render(bool $scroll): string
    {
        return (string) view('meilifacets::components.reset', [
            'listing' => new readonly class
            {
                public function isPristine(): bool
                {
                    return false;
                }
            },
            'hook' => fn (string $name): HtmlString => new HtmlString('data-meili="'.$name.'"'),
            'scrollMark' => fn (): HtmlString => new HtmlString($scroll ? Contract::SCROLL_ATTRIBUTE : ''),
        ])->render();
    }
}
