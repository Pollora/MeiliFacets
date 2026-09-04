<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class Pagination
{
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

    /**
     * @return list<int>
     */
    public function numbers(): array
    {
        return range(ListingState::FIRST_PAGE, max($this->pages(), ListingState::FIRST_PAGE));
    }
}
