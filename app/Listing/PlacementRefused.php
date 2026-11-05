<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use LogicException;

final class PlacementRefused extends LogicException
{
    public static function sharedName(string $first, string $second, string $name, string $listing): self
    {
        return new self(sprintf(
            'Facets "%s" and "%s" answer to the same name "%s" in listing "%s". Declare `name:` on all but one of them.',
            $first, $second, $name, $listing,
        ));
    }

    /**
     * @param  list<string>  $declared
     */
    public static function unknownFacet(string $name, string $listing, array $declared): self
    {
        return new self("No facet named \"{$name}\" in listing \"{$listing}\". Declared: ".implode(', ', $declared).'.');
    }

    public static function otherKind(string $name, string $listing, string $kind): self
    {
        return new self("\"{$name}\" in listing \"{$listing}\" is a {$kind}: place it with the component of its own kind.");
    }

    public static function facetRenderedTwice(string $facet): self
    {
        return new self(
            "Facet \"{$facet}\" is rendered twice on this page: its inputs and ids "
            .'would be duplicated. Place it on its own before <x-meilifacets::listing.facets>, which shows what is left.'
        );
    }

    public static function sortRenderedTwice(string $listing): self
    {
        return new self(
            "The sort of listing \"{$listing}\" is rendered twice on this page: its list and ids "
            .'would be duplicated. Render <x-meilifacets::listing.sort> once per page.'
        );
    }
}
