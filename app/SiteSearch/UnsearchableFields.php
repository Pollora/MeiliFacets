<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use LogicException;

final class UnsearchableFields extends LogicException
{
    /**
     * @param  list<string>  $fields
     */
    public static function for(string $postType, array $fields): self
    {
        return new self(
            "Post type \"{$postType}\" is searched on fields the index does not search: ".implode(', ', $fields).'. '
            .'Meilisearch refuses the whole query: add them to SearchableAttributes or remove them from searchOn.'
        );
    }
}
