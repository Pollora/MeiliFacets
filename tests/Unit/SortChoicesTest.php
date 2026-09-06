<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\View\SortChoices;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SortChoicesTest extends TestCase
{
    private SortChoices $choices;

    protected function setUp(): void
    {
        $this->choices = new SortChoices('meilifacets-products-sort');
    }

    /** Without it, no way back to the engine's own order once a sort is picked. */
    #[Test]
    public function it_opens_with_the_default_order(): void
    {
        $first = $this->choices->of($this->sorts(), null)[0];

        $this->assertSame('', $first->value);
        $this->assertTrue($first->selected);
    }

    #[Test]
    public function it_keeps_the_order_the_listing_declared(): void
    {
        $values = array_column($this->choices->of($this->sorts(), null), 'value');

        $this->assertSame(['', 'price_asc', 'newest'], $values);
    }

    #[Test]
    public function it_marks_the_current_sort_and_no_other(): void
    {
        $selected = array_filter($this->choices->of($this->sorts(), 'newest'), fn ($choice) => $choice->selected);

        $this->assertCount(1, $selected);
        $this->assertSame('newest', array_values($selected)[0]->value);
    }

    /** aria-activedescendant needs an id per option, unique across listings. */
    #[Test]
    public function it_gives_every_choice_an_identifier(): void
    {
        $ids = array_column($this->choices->of($this->sorts(), null), 'id');

        $this->assertSame([
            'meilifacets-products-sort-default',
            'meilifacets-products-sort-price_asc',
            'meilifacets-products-sort-newest',
        ], $ids);
    }

    /**
     * @return array<string, Sort>
     */
    private function sorts(): array
    {
        return [
            'price_asc' => new Sort('Price, low to high', ['metas._price:asc']),
            'newest' => new Sort('New arrivals', ['post_date:desc']),
        ];
    }
}
