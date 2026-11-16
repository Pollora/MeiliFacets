<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Modules\MeiliFacets\Contracts\EngineUnavailable;
use Modules\MeiliFacets\Search\MeilisearchEngine;

/**
 * @param  list<array<string, mixed>>  $results
 */
function meilisearchEngineClientAnswering(array $results): Client
{
    return new class($results) extends Client
    {
        /** @param  list<array<string, mixed>>  $results */
        public function __construct(private readonly array $results) {}

        public function multiSearch(array $queries = [], ?MultiSearchFederation $federation = null): array
        {
            return ['results' => $this->results];
        }
    };
}

it('hands each answer back under its key', function (): void {
    $engine = new MeilisearchEngine(meilisearchEngineClientAnswering([['totalHits' => 3], ['totalHits' => 1]]), 'posts');

    expect($engine->multiSearch(['results' => [], 'count_brand' => []]))
        ->toBe(['results' => ['totalHits' => 3], 'count_brand' => ['totalHits' => 1]]);
});

it('counts a short answer as an outage', function (): void {
    $engine = new MeilisearchEngine(meilisearchEngineClientAnswering([['totalHits' => 3]]), 'posts');

    $this->expectException(EngineUnavailable::class);

    $engine->multiSearch(['results' => [], 'count_brand' => []]);
});
