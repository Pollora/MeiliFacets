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
}
