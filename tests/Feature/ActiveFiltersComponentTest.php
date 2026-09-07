<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\HtmlString;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A bare digit names nothing: the badge has to say what it counts. */
final class ActiveFiltersComponentTest extends TestCase
{
    #[Test]
    public function it_names_what_it_counts(): void
    {
        // A word after the digit, whatever the language the site runs in.
        $this->assertMatchesRegularExpression('/>1 \w+/u', $this->render(1));
        $this->assertMatchesRegularExpression('/>2 \w+/u', $this->render(2));
    }

    #[Test]
    public function it_agrees_with_what_it_counts(): void
    {
        $this->assertNotSame(strip_tags($this->render(1)), strip_tags($this->render(2)));
        $this->assertNotSame(
            preg_replace('/\d/', '', strip_tags($this->render(1))),
            preg_replace('/\d/', '', strip_tags($this->render(2)))
        );
    }

    /** Rendered even at zero: the client reveals it, it creates nothing. */
    #[Test]
    public function it_is_rendered_and_hidden_when_nothing_is_filtered(): void
    {
        $html = $this->render(0);

        $this->assertStringContainsString('hidden', $html);
        $this->assertStringContainsString('data-meili="active-filters"', $html);
    }

    private function render(int $count): string
    {
        return (string) view('meilifacets::components.active-filters', [
            'count' => $count,
            'label' => trans_choice(':count active filter|:count active filters', $count),
            'hook' => fn (string $name): HtmlString => new HtmlString('data-meili="'.$name.'"'),
        ])->render();
    }
}
