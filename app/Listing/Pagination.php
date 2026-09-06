<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class Pagination
{
    public const int WINDOW = 7;

    public function __construct(
        public int $current,
        public int $perPage,
        public int $total,
    ) {}

    public function pages(): int
    {
        return $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : ListingState::FIRST_PAGE;
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
        return min($this->current + 1, $this->pages());
    }

    public function hasPages(): bool
    {
        return $this->pages() > ListingState::FIRST_PAGE;
    }

    /**
     * Slots past the last page stay empty rather than absent: the client fills
     * them when a filter widens the result set.
     *
     * @return list<?int>
     */
    public function window(): array
    {
        return array_pad($this->numbers(), self::WINDOW, null);
    }

    /**
     * @return list<int>
     */
    private function numbers(): array
    {
        $last = max($this->pages(), ListingState::FIRST_PAGE);
        $width = min(self::WINDOW, $last);
        $first = min(
            max($this->current - intdiv($width, 2), ListingState::FIRST_PAGE),
            $last - $width + 1
        );

        return range($first, $first + $width - 1);
    }
}
