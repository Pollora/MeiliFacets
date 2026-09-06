<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface DefaultTerms
{
    /** The term a taxonomy falls back to, or null when it has none. */
    public function slugOf(string $taxonomy): ?string;
}
