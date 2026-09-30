<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Meilisearch\Client;
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
        $client = $this->client ?? throw EngineUnavailable::unconfigured();
        $searches = array_map(new MeilisearchQuery($this->index)->from(...), $queries);

        try {
            return $client->multiSearch($searches)['results'] ?? [];
        } catch (Throwable $failure) {
            throw EngineUnavailable::unreachable($failure);
        }
    }
}
