<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeRequest;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;

// Loaded by `WordPress::boot()`, from a test that runs in its own process only.

if (! function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $container = Container::getInstance();

        return $abstract === null ? $container : $container->make($abstract);
    }
}

if (! function_exists('config')) {
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        return WordPress::$config[$key] ?? $default;
    }
}

if (! function_exists('request')) {
    function request(): FakeRequest
    {
        return new FakeRequest(WordPress::$query, WordPress::$queryString);
    }
}

if (! function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return WordPress::call('public_path', [$path]);
    }
}

if (! function_exists('module_path')) {
    function module_path(string $name, string $path = ''): string
    {
        return WordPress::call('module_path', [$name, $path]);
    }
}

if (! function_exists('report')) {
    function report(Throwable|string $exception): void
    {
        WordPress::call('report', [$exception], static fn (): null => null);
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed
    {
        return WordPress::call('apply_filters', [$hook_name, $value, ...$args], static fn (): mixed => $value);
    }
}

if (! function_exists('did_action')) {
    function did_action(string $hook_name): int
    {
        return WordPress::call('did_action', [$hook_name], static fn (): int => 0);
    }
}

if (! function_exists('get_locale')) {
    function get_locale(): string
    {
        return WordPress::call('get_locale', [], static fn (): string => 'en_US');
    }
}

if (! function_exists('wp_cache_get')) {
    function wp_cache_get(int|string $key, string $group = '', bool $force = false, ?bool &$found = null): mixed
    {
        return false;
    }
}

if (! function_exists('get_pagenum_link')) {
    function get_pagenum_link(int $pagenum = 1, bool $escape = true): string
    {
        return WordPress::call('get_pagenum_link', [$pagenum, $escape]);
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): mixed
    {
        return parse_url($url, $component);
    }
}

if (! function_exists('get_query_var')) {
    function get_query_var(string $query_var, mixed $default_value = ''): mixed
    {
        return WordPress::call('get_query_var', [$query_var, $default_value], static fn (): mixed => $default_value);
    }
}

if (! function_exists('is_paged')) {
    function is_paged(): bool
    {
        return WordPress::call('is_paged', [], static fn (): bool => false);
    }
}
