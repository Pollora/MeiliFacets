<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

// Searched, never filtered: not a `ProductMeta`, whose cases are all filterable.
enum SearchedMeta: string
{
    case Sku = '_sku';

    public function path(): string
    {
        return DocumentField::Metas->path($this->value);
    }
}
