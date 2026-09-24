<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class SiteLocale
{
    /** As `CoreWordPressTranslator::locale()`: without a database Pollora loads neither `get_locale()` nor the object cache. */
    public static function current(): string
    {
        $locale = function_exists('get_locale') && function_exists('wp_cache_get') ? get_locale() : '';

        return $locale === '' ? app()->getLocale() : $locale;
    }
}
