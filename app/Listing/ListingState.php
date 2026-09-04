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
    ) {}

    /**
     * @return list<string>
     */
    public function selected(string $taxonomy): array
    {
        return $this->facets[$taxonomy] ?? [];
    }

    public function holds(string $taxonomy, string $value): bool
    {
        return in_array($value, $this->selected($taxonomy), true);
    }

    public function activeFilters(): int
    {
        return array_sum(array_map(count(...), $this->facets));
    }

    public function isPristine(): bool
    {
        return $this->facets === []
            && $this->sort === null
            && $this->query === ''
            && $this->page === self::FIRST_PAGE;
    }
}
