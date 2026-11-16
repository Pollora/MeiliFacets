<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Illuminate\Support\Arr;

/** What `request()` hands back: the parsed query and the raw server variables. */
final class FakeRequest
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function __construct(private readonly array $query = [], private readonly string $queryString = '') {}

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function server(string $key): ?string
    {
        return Arr::get(['QUERY_STRING' => $this->queryString], $key);
    }
}
