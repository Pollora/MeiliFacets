<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Stylesheet;
use Modules\MeiliFacets\View\ClientStylesheet;
use Tests\TestCase;
use WP_Styles;

trait ClientStylesheetSupport
{
    private const string LINK = "id='".Stylesheet::Listing->value."-css'";

    private static function printed(callable $print): string
    {
        ob_start();
        $print();

        return (string) ob_get_clean();
    }

    /** @param  Closure(): string  $render */
    private function withFreshStyles(Closure $render): string
    {
        $shared = $GLOBALS['wp_styles'] ?? null;
        $GLOBALS['wp_styles'] = new WP_Styles;

        try {
            return $render();
        } finally {
            $GLOBALS['wp_styles'] = $shared;
        }
    }

    /** @param  Closure(): string  $render */
    private function afterTheHead(Closure $render): string
    {
        global $wp_actions;

        $actual = $wp_actions['wp_head'] ?? 0;
        $wp_actions['wp_head'] = 1;

        try {
            return $render();
        } finally {
            $wp_actions['wp_head'] = $actual;
        }
    }
}

uses(TestCase::class, ClientStylesheetSupport::class);

afterEach(function (): void {
    $this->app->forgetScopedInstances();
});

it('leaves the stylesheet off a page without a listing', function (): void {
    $head = $this->withFreshStyles(static function (): string {
        do_action('wp_enqueue_scripts');

        return self::printed('wp_print_styles');
    });

    $this->assertStringNotContainsString(self::LINK, $head);
});

/** Layouts render their sections first, as `@extends` does. */
it('prints the stylesheet in the head of a layout rendering a listing', function (): void {
    $page = $this->withFreshStyles(static fn (): string => Blade::render(<<<'BLADE'
        @section('content')<x-meilifacets::listing name="products" />@endsection
        <head>@php do_action('wp_enqueue_scripts'); wp_print_styles(); @endphp</head>
        <body>@yield('content')</body>
        BLADE));

    expect($page)->toContain(self::LINK);
    expect(strpos($page, (string) self::LINK))->toBeLessThan(strpos($page, '</head>'));
});

/** A header renders after the `<head>` its layout printed. */
it('prints the search stylesheet before a search rendered after the head', function (): void {
    $body = $this->withFreshStyles(fn (): string => $this->afterTheHead(static function (): string {
        new ClientStylesheet()->register();

        return Blade::render('<x-meilifacets::search />');
    }));

    $link = "id='".Stylesheet::SiteSearch->value."-css'";

    expect($body)->toContain($link);
    expect(strpos($body, $link))->toBeLessThan(strpos($body, 'data-meili="search"'));
    $this->assertStringNotContainsString(self::LINK, $body);
});

it('prints the stylesheet once before listings rendered after the head', function (): void {
    $body = $this->withFreshStyles(fn (): string => $this->afterTheHead(static function (): string {
        $stylesheet = new ClientStylesheet;
        $stylesheet->register();

        return self::printed(static fn () => $stylesheet->require(Stylesheet::Listing)).self::printed(static fn () => $stylesheet->require(Stylesheet::Listing));
    }));

    expect(substr_count($body, self::LINK))->toBe(1);
});

it('lets a theme dequeue the stylesheet', function (): void {
    $head = $this->withFreshStyles(static function (): string {
        $stylesheet = new ClientStylesheet;
        $stylesheet->require(Stylesheet::Listing);
        $stylesheet->register();
        wp_dequeue_style(Stylesheet::Listing->value);

        return self::printed('wp_print_styles');
    });

    $this->assertStringNotContainsString(self::LINK, $head);
});

it('lets a theme deregister the stylesheet of a listing rendered after the head', function (): void {
    $body = $this->withFreshStyles(fn (): string => $this->afterTheHead(static function (): string {
        $stylesheet = new ClientStylesheet;
        $stylesheet->register();
        wp_deregister_style(Stylesheet::Listing->value);

        return self::printed(static fn () => $stylesheet->require(Stylesheet::Listing));
    }));

    $this->assertStringNotContainsString(self::LINK, $body);
});
