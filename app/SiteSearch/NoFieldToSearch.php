<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use LogicException;

final class NoFieldToSearch extends LogicException
{
    public static function for(string $postType): self
    {
        return new self(
            "Post type \"{$postType}\" has no field to search: the search order (SearchableAttributes) keeps none "
            .'of the fields it is searched on. Keep one of them in the order, or remove the type from SearchableTypes.'
        );
    }
}
