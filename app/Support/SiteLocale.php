<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class SiteLocale
{
    /** WordPress declares `get_locale()` only once installed: Pollora stops loading it on a site without a database. */
    public static function current(): string
    {
        return function_exists('get_locale') ? get_locale() : app()->getLocale();
    }
}
