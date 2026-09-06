<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface TermScope
{
    /**
     * Slugs of the terms directly under the one the request is on, or under the
     * root when it is on none.
     *
     * @return list<string>
     */
    public function childrenOf(string $taxonomy): array;
}
