<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Pagination;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    #[Test]
    public function it_offsets_a_page_by_the_ones_before_it(): void
    {
        $this->assertSame(0, (new Pagination(1, 16, 33))->offset());
        $this->assertSame(16, (new Pagination(2, 16, 33))->offset());
        $this->assertSame(32, (new Pagination(3, 16, 33))->offset());
    }

    #[Test]
    public function it_rounds_a_partial_last_page_up(): void
    {
        $this->assertSame(3, (new Pagination(1, 16, 33))->pages());
        $this->assertSame(1, (new Pagination(1, 16, 12))->pages());
    }

    #[Test]
    public function it_reports_a_single_page_when_nothing_matches(): void
    {
        $pagination = new Pagination(1, 16, 0);

        $this->assertSame(0, $pagination->pages());
        $this->assertFalse($pagination->hasNext());
        $this->assertFalse($pagination->hasPrevious());
    }

    #[Test]
    public function it_never_divides_by_a_page_size_of_zero(): void
    {
        $this->assertSame(1, (new Pagination(1, 0, 40))->pages());
    }

    #[Test]
    public function it_clamps_the_neighbours_of_the_current_page(): void
    {
        $first = new Pagination(1, 16, 40);
        $last = new Pagination(3, 16, 40);

        $this->assertSame(1, $first->previous());
        $this->assertSame(2, $first->next());
        $this->assertSame(3, $last->next());
        $this->assertFalse($last->hasNext());
    }

    #[Test]
    public function it_lists_every_page_when_they_fit_in_the_window(): void
    {
        $this->assertSame([1, 2, 3, null, null, null, null], (new Pagination(1, 16, 40))->window());
    }

    /** A fixed count of slots: the client fills them, it never adds any. */
    #[Test]
    public function it_always_offers_the_same_number_of_slots(): void
    {
        foreach ([0, 40, 5000] as $total) {
            $this->assertCount(Pagination::WINDOW, (new Pagination(1, 16, $total))->window());
        }
    }

    #[Test]
    public function it_centres_the_window_on_the_current_page(): void
    {
        $this->assertSame([47, 48, 49, 50, 51, 52, 53], (new Pagination(50, 16, 5000))->window());
    }

    #[Test]
    public function it_clamps_the_window_at_both_ends(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], (new Pagination(1, 16, 5000))->window());
        $this->assertSame([307, 308, 309, 310, 311, 312, 313], (new Pagination(313, 16, 5000))->window());
    }

    #[Test]
    public function it_leaves_the_slots_past_the_last_page_empty(): void
    {
        $this->assertSame([1, 2, null, null, null, null, null], (new Pagination(1, 16, 20))->window());
        $this->assertSame([1, null, null, null, null, null, null], (new Pagination(1, 16, 0))->window());
    }

    #[Test]
    public function it_knows_when_there_is_nothing_to_paginate(): void
    {
        $this->assertFalse((new Pagination(1, 16, 12))->hasPages());
        $this->assertTrue((new Pagination(1, 16, 20))->hasPages());
    }
}
