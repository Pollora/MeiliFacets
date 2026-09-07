<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class Pagination
{
    public const int SLOTS = 7;

    /** Never outside the pages that exist: a URL carries whatever a visitor pasted into it. */
    public int $current;

    public function __construct(
        int $current,
        public int $perPage,
        public int $total,
        public int $reachable,
    ) {
        $this->current = min(
            max($current, ListingState::FIRST_PAGE),
            max($this->pages(), ListingState::FIRST_PAGE)
        );
    }

    /** Meilisearch answers `200` with no hit past its `maxTotalHits`, while still announcing the pages it refuses. */
    public function pages(): int
    {
        if ($this->perPage <= 0) {
            return ListingState::FIRST_PAGE;
        }

        return (int) ceil(min($this->total, $this->reachable) / $this->perPage);
    }

    public function offset(): int
    {
        return ($this->current - ListingState::FIRST_PAGE) * $this->perPage;
    }

    public function hasPrevious(): bool
    {
        return $this->current > ListingState::FIRST_PAGE;
    }

    public function hasNext(): bool
    {
        return $this->current < $this->pages();
    }

    public function previous(): int
    {
        return max($this->current - 1, ListingState::FIRST_PAGE);
    }

    public function next(): int
    {
        return min($this->current + 1, max($this->pages(), ListingState::FIRST_PAGE));
    }

    public function hasPages(): bool
    {
        return $this->pages() > ListingState::FIRST_PAGE;
    }

    /**
     * @return list<?int>
     */
    public function slots(): array
    {
        return array_pad($this->numbers(), self::SLOTS, null);
    }

    /**
     * @return list<int>
     */
    private function numbers(): array
    {
        $last = max($this->pages(), ListingState::FIRST_PAGE);
        $width = min(self::SLOTS, $last);
        $first = min(
            max($this->current - intdiv($width, 2), ListingState::FIRST_PAGE),
            $last - $width + 1
        );

        return range($first, $first + $width - 1);
    }
}
