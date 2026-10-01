<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** The stylesheets the module ships, each under the handle a theme dequeues it by. */
enum Stylesheet: string
{
    case Listing = 'meilifacets';
    case SiteSearch = 'meilifacets-site-search';

    /** Where `php artisan module:publish MeiliFacets` puts the stylesheet. */
    public function source(): string
    {
        return match ($this) {
            self::Listing => 'modules/meilifacets/css/meilifacets.css',
            self::SiteSearch => 'modules/meilifacets/css/site-search.css',
        };
    }
}
