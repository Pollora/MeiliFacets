<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\HtmlString;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\ResetShape;
use Tests\TestCase;

trait ScrollOptionHelpers
{
    private function render(bool $scroll): string
    {
        return (string) view('meilifacets::components.listing.reset', [
            'hasNothingToClear' => fn (): bool => false,
            'shape' => ResetShape::Text,
            'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
            'scrollMark' => fn (): HtmlString => new HtmlString($scroll ? Contract::ScrollAttribute->value : ''),
        ])->render();
    }
}

/**
 * Bringing the listing back into view is asked for on the component, one at a
 * time. A theme that says nothing gets a page that holds still.
 */
uses(TestCase::class, ScrollOptionHelpers::class);

it('marks nothing by default', function (): void {
    expect($this->render(false))->not->toContain(Contract::ScrollAttribute->value);
});

it('marks the component that asks', function (): void {
    expect($this->render(true))->toContain(Contract::ScrollAttribute->value);
});
