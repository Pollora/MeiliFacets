<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum DocumentField: string
{
    case Terms = 'terms';
    case Facets = 'facets';
    case Metas = 'metas';

    public function path(string $key): string
    {
        return $this->value.'.'.$key;
    }
}
