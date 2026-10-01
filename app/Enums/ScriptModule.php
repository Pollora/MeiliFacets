<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** The browser bundles the module ships, each under the id its client reads its data from. */
enum ScriptModule: string
{
    case Listing = '@meilifacets/listing';
    case SiteSearch = '@meilifacets/site-search';

    /** Where `php artisan module:publish MeiliFacets` puts the bundle. */
    public function source(): string
    {
        return match ($this) {
            self::Listing => 'modules/meilifacets/dist/listing.js',
            self::SiteSearch => 'modules/meilifacets/dist/site-search.js',
        };
    }

    /** The key under which the published data holds one description per root, by name. */
    public function roots(): string
    {
        return match ($this) {
            self::Listing => 'listings',
            self::SiteSearch => 'searches',
        };
    }
}
