<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use LogicException;

final class SearchTypeRefused extends LogicException
{
    /**
     * @param  list<string>  $accepted
     */
    public static function for(string $postType, array $accepted): self
    {
        return new self(
            "Post type \"{$postType}\" is not searchable: declare it in SearchableTypes and index it in MeiliScout. "
            .'Searchable here: '.self::listed($accepted).'.'
        );
    }

    public static function unregistered(string $postType): self
    {
        return new self("Post type \"{$postType}\" is not registered: WordPress has no labels, archive or taxonomies for it.");
    }

    /**
     * @param  list<string>  $accepted
     */
    private static function listed(array $accepted): string
    {
        return $accepted === [] ? 'none' : implode(', ', $accepted);
    }
}
