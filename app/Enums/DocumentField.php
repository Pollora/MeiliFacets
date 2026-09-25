<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum DocumentField: string
{
    case Title = 'post_title';
    case Excerpt = 'excerpt';
    case Terms = 'terms';
    case Facets = 'facets';
    case Metas = 'metas';
    case Card = 'card';
    case Price = 'price';
    case Labels = 'labels';
    case Content = 'content';

    public function path(string $key): string
    {
        return $this->value.'.'.$key;
    }
}
