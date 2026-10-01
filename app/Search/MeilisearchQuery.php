<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Meilisearch\Contracts\SearchQuery;

final readonly class MeilisearchQuery
{
    private const array OPTIONS = ['filter', 'facets', 'sort', 'attributesToRetrieve', 'attributesToSearchOn'];

    public function __construct(private string $index) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function from(array $query): SearchQuery
    {
        $search = (new SearchQuery)
            ->setIndexUid($this->index)
            ->setQuery((string) ($query['q'] ?? ''))
            ->setHitsPerPage((int) ($query['hitsPerPage'] ?? 0))
            ->setPage((int) ($query['page'] ?? 1));

        foreach (self::OPTIONS as $option) {
            $search = $this->apply($search, $option, $query[$option] ?? null);
        }

        return $search;
    }

    private function apply(SearchQuery $search, string $option, mixed $value): SearchQuery
    {
        return match (true) {
            $value === null, $value === '', $value === [] => $search,
            $option === 'filter' => $search->setFilter([$value]),
            $option === 'facets' => $search->setFacets($value),
            $option === 'sort' => $search->setSort($value),
            $option === 'attributesToSearchOn' => $search->setAttributesToSearchOn($value),
            default => $search->setAttributesToRetrieve($value),
        };
    }
}
