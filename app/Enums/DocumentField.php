<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum DocumentField: string
{
    case Id = 'ID';
    case PostType = 'post_type';
    case Status = 'post_status';
    case Title = 'post_title';
    case Excerpt = 'excerpt';
    case Terms = 'terms';
    case Facets = 'facets';
    case Metas = 'metas';
    case Card = 'card';
    case Price = 'price';
    case Labels = 'labels';
    case Content = 'content';
    case Kind = 'document_kind';
    case ParentId = 'parent_id';
    case InStock = 'in_stock';

    public function path(string $key): string
    {
        return $this->value.'.'.$key;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function paths(array $keys): array
    {
        return array_map($this->path(...), $keys);
    }
}
