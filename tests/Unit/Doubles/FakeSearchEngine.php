<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\SearchEngine;

final class FakeSearchEngine implements SearchEngine
{
    public int $calls = 0;

    /** @var list<array<string, array<string, mixed>>> */
    public array $received = [];

    /**
     * @param  array<string, array<string, mixed>>  $responses  keyed like the queries
     */
    public function __construct(private readonly array $responses = []) {}

    /**
     * @param  array<string, array<string, mixed>>  $queries
     * @return array<string, array<string, mixed>>
     */
    public function multiSearch(array $queries): array
    {
        $this->calls++;
        $this->received[] = $queries;

        return array_intersect_key($this->responses, $queries);
    }
}
