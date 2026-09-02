<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\IndexAttributes;

final readonly class EmptyIndexAttributes implements IndexAttributes
{
    /**
     * @return list<string>
     */
    public function filterable(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return [];
    }
}
