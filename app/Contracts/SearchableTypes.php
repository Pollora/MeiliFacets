<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\SiteSearch\SearchableType;

interface SearchableTypes
{
    /**
     * @return array<string, SearchableType> keyed by post type
     */
    public function all(): array;
}
