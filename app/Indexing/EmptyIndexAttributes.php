<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\IndexAttributes;

final readonly class EmptyIndexAttributes implements IndexAttributes
{
    public function exactlyMatched(): array
    {
        return [];
    }

    public function filterable(): array
    {
        return [];
    }

    public function sortable(): array
    {
        return [];
    }

    public function displayed(): array
    {
        return [];
    }
}
