<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

/**
 * @phpstan-type Term array{term_id: int, name: string, slug: string, taxonomy: string, term_taxonomy_id: int, parent: int}
 */
interface TermHierarchy
{
    /**
     * Terms a given term descends from, closest first.
     *
     * @return list<Term>
     */
    public function ancestorsOf(int $termId, string $taxonomy): array;

    /**
     * Slugs of the terms directly under the given one. The root is `0`.
     *
     * @return list<string>
     */
    public function childrenOf(int $termId, string $taxonomy): array;
}
