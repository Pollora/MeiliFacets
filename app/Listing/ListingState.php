<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class ListingState
{
    public const int FIRST_PAGE = 1;

    /**
     * @param  array<string, list<string>>  $facets  taxonomy to selected slugs
     */
    public function __construct(
        public array $facets = [],
        public ?string $sort = null,
        public int $page = self::FIRST_PAGE,
        public string $query = '',
        public Range $price = new Range,
    ) {}

    /**
     * @return list<string>
     */
    public function selected(string $taxonomy): array
    {
        return $this->facets[$taxonomy] ?? [];
    }

    public function isSelected(string $taxonomy, string $value): bool
    {
        return in_array($value, $this->selected($taxonomy), true);
    }

    public function activeFilterCount(): int
    {
        return array_sum(array_map(count(...), $this->facets)) + ($this->price->isEmpty() ? 0 : 1);
    }

    public function isNarrowed(): bool
    {
        return $this->facets !== [] || $this->query !== '' || ! $this->price->isEmpty();
    }

    public function isPristine(): bool
    {
        return $this->facets === []
            && $this->sort === null
            && $this->query === ''
            && $this->page === self::FIRST_PAGE
            && $this->price->isEmpty();
    }
}
