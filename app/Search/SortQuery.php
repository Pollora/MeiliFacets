<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\SortFilter;

final readonly class SortQuery implements FilterQuery
{
    public const string KEY = 'sorted';

    /**
     * @param  array<string, SortFilter>  $filters  sort key to the filter it carries
     */
    public function __construct(private array $filters) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function fields(): array
    {
        return array_values(array_unique(array_map(static fn (SortFilter $filter): string => $filter->field, $this->filters)));
    }

    public function clause(ListingState $state): string
    {
        $filter = $state->sort === null ? null : ($this->filters[$state->sort] ?? null);

        return $filter instanceof SortFilter ? $filter->clause() : '';
    }

    public function isMeasuredApart(ListingState $state): bool
    {
        return false;
    }

    /**
     * @param  array<string, array<string, int>>  $distribution  field to value to count, off the main search
     * @return array<string, int> sort key to the hits it would keep
     */
    public function matchesIn(array $distribution): array
    {
        return array_map(static fn (SortFilter $filter): int => $filter->matchesIn($distribution), $this->filters);
    }
}
