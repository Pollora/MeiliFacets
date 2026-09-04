<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface TermLabels
{
    /**
     * Display names for the given slugs, keyed by slug. A slug without a term is
     * simply absent.
     *
     * @param  list<string>  $slugs
     * @return array<string, string>
     */
    public function of(string $taxonomy, array $slugs): array;
}
