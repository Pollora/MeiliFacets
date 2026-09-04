<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface SearchEngine
{
    /**
     * Searches sent as one request, answers handed back under the caller's keys.
     *
     * @param  array<string, array<string, mixed>>  $queries
     * @return array<string, array<string, mixed>>
     */
    public function multiSearch(array $queries): array;
}
