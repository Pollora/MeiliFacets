<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Modules\MeiliFacets\Search\EngineUnavailable;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MeilisearchEngineTest extends TestCase
{
    private const string INDEX = 'posts';

    #[Test]
    public function it_hands_each_answer_back_under_its_key(): void
    {
        $engine = new MeilisearchEngine($this->clientAnswering([['totalHits' => 3], ['totalHits' => 1]]), self::INDEX);

        $this->assertSame(
            ['results' => ['totalHits' => 3], 'count_brand' => ['totalHits' => 1]],
            $engine->multiSearch(['results' => [], 'count_brand' => []])
        );
    }

    #[Test]
    public function it_counts_a_short_answer_as_an_outage(): void
    {
        $engine = new MeilisearchEngine($this->clientAnswering([['totalHits' => 3]]), self::INDEX);

        $this->expectException(EngineUnavailable::class);

        $engine->multiSearch(['results' => [], 'count_brand' => []]);
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function clientAnswering(array $results): Client
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
}
