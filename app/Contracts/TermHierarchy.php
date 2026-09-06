<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface TermHierarchy
{
    /**
     * Terms a given term descends from, closest first.
     *
     * @return list<array<string, mixed>>
     */
    public function ancestorsOf(int $termId, string $taxonomy): array;

    /**
     * Slugs of the terms directly under the given one. The root is `0`.
     *
     * @return list<string>
     */
    public function childrenOf(int $termId, string $taxonomy): array;
}
