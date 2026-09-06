<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Throwable;

final readonly class MeilisearchEngine implements SearchEngine
{
    public function __construct(private ?Client $client, private string $index) {}

    /**
     * @param  array<string, array<string, mixed>>  $queries
     * @return array<string, array<string, mixed>>
     */
    public function multiSearch(array $queries): array
    {
        if ($queries === []) {
            return [];
        }

        $responses = $this->send(array_values($queries));

        return array_combine(array_keys($queries), array_slice($responses, 0, count($queries)));
    }

    /**
     * @param  list<array<string, mixed>>  $queries
     * @return list<array<string, mixed>>
     */
    private function send(array $queries): array
    {
        $client = $this->client ?? throw SearchFailed::unconfigured();
        $searches = array_map($this->toSearchQuery(...), $queries);

        try {
            return $client->multiSearch($searches)['results'] ?? [];
        } catch (Throwable $failure) {
            throw SearchFailed::unreachable($failure);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function toSearchQuery(array $query): SearchQuery
    {
        $search = (new SearchQuery)
            ->setIndexUid($this->index)
            ->setQuery((string) ($query['q'] ?? ''))
            ->setHitsPerPage((int) ($query['hitsPerPage'] ?? 0))
            ->setPage((int) ($query['page'] ?? 1));

        foreach (['filter', 'facets', 'sort', 'attributesToRetrieve'] as $option) {
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
            default => $search->setAttributesToRetrieve($value),
        };
    }
}
