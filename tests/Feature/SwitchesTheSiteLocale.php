<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

trait SwitchesTheSiteLocale
{
    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    protected function underSiteLocale(string $locale, callable $read): mixed
    {
        $filter = static fn (): string => $locale;
        add_filter('locale', $filter);

        try {
            return $read();
        } finally {
            remove_filter('locale', $filter);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    protected function underLocales(string $laravel, string $wordPress, callable $read): mixed
    {
        $appLocale = app()->getLocale();
        app()->setLocale($laravel);

        try {
            return $this->underSiteLocale($wordPress, $read);
        } finally {
            app()->setLocale($appLocale);
        }
    }
}
