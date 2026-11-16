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

if (! function_exists('esc_url')) {
    function esc_url(string $url, ?array $protocols = null, string $_context = 'display'): string
    {
        return WordPress::call('esc_url', [$url], static fn (): string => $url);
    }
}

if (! function_exists('asset')) {
    function asset(string $path): string
    {
        return 'https://example.test/'.ltrim($path, '/');
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true
    {
        WordPress::call('add_filter', [$hook_name, $callback, $priority], static fn (): null => null);

        return true;
    }
}

if (! function_exists('remove_filter')) {
    function remove_filter(string $hook_name, callable $callback, int $priority = 10): bool
    {
        WordPress::call('remove_filter', [$hook_name, $callback, $priority], static fn (): null => null);

        return true;
    }
}

if (! function_exists('has_action')) {
    function has_action(string $hook_name, callable|string|array|false $callback = false): bool|int
    {
        return WordPress::call('has_action', [$hook_name, $callback], static fn (): int => 10);
    }
}

if (! function_exists('wp_robots')) {
    function wp_robots(): void
    {
        WordPress::call('wp_robots', [], static fn (): null => null);
    }
}

if (! function_exists('wp_get_document_title')) {
    function wp_get_document_title(): string
    {
        return WordPress::call('wp_get_document_title', []);
    }
}

if (! function_exists('wp_enqueue_script_module')) {
    function wp_enqueue_script_module(string $id, string $src = '', array $deps = [], string|false|null $version = false, array $args = []): void
    {
        WordPress::call('wp_enqueue_script_module', [$id, $src, $deps, $version, $args], static fn (): null => null);
    }
}

if (! function_exists('wp_register_style')) {
    function wp_register_style(string $handle, string|false $src, array $deps = [], string|bool|null $ver = false, string $media = 'all'): bool
    {
        WordPress::call('wp_register_style', [$handle, $src, $deps, $ver], static fn (): null => null);

        return true;
    }
}

if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all'): void
    {
        WordPress::call('wp_enqueue_style', [$handle], static fn (): null => null);
    }
}

if (! function_exists('wp_print_styles')) {
    function wp_print_styles(string|array|false $handles = false): array
    {
        WordPress::call('wp_print_styles', [$handles], static fn (): null => null);

        return [];
    }
}

if (! function_exists('has_filter')) {
    function has_filter(string $hook_name, callable|string|array|false $callback = false): bool|int
    {
        return WordPress::call('has_filter', [$hook_name, $callback], static fn (): bool => false);
    }
}

if (! function_exists('is_singular')) {
    function is_singular(string|array $post_types = ''): bool
    {
        return WordPress::call('is_singular', [$post_types], static fn (): bool => false);
    }
}

if (! function_exists('wp_get_canonical_url')) {
    function wp_get_canonical_url(int|WP_Post|null $post = null): string|false
    {
        return WordPress::call('wp_get_canonical_url', [$post], static fn (): bool => false);
    }
}
