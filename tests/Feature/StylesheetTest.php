<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\View\Stylesheet;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Styles;

final class StylesheetTest extends TestCase
{
    private const string LINK = "id='".Stylesheet::HANDLE."-css'";

    /** The suite shares one application: a listing resolved here would reach a later class. */
    protected function tearDown(): void
    {
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_leaves_the_stylesheet_off_a_page_without_a_listing(): void
    {
        $head = $this->withFreshStyles(static function (): string {
            do_action('wp_enqueue_scripts');

            return self::printed('wp_print_styles');
        });

        $this->assertStringNotContainsString(self::LINK, $head);
    }

    /** Layouts render their sections first, as `@extends` does. */
    #[Test]
    public function it_prints_the_stylesheet_in_the_head_of_a_layout_rendering_a_listing(): void
    {
        $page = $this->withFreshStyles(static fn (): string => Blade::render(<<<'BLADE'
            @section('content')<x-meilifacets::listing />@endsection
            <head>@php do_action('wp_enqueue_scripts'); wp_print_styles(); @endphp</head>
            <body>@yield('content')</body>
            BLADE));

        $this->assertStringContainsString(self::LINK, $page);
        $this->assertLessThan(strpos($page, '</head>'), strpos($page, self::LINK));
    }

    #[Test]
    public function it_prints_the_stylesheet_once_before_listings_rendered_after_the_head(): void
    {
        $body = $this->withFreshStyles(fn (): string => $this->afterTheHead(static function (): string {
            $stylesheet = new Stylesheet;
            $stylesheet->register();

            return self::printed($stylesheet->require(...)).self::printed($stylesheet->require(...));
        }));

        $this->assertSame(1, substr_count($body, self::LINK));
    }

    #[Test]
    public function it_lets_a_theme_dequeue_the_stylesheet(): void
    {
        $head = $this->withFreshStyles(static function (): string {
            $stylesheet = new Stylesheet;
            $stylesheet->require();
            $stylesheet->register();
            wp_dequeue_style(Stylesheet::HANDLE);

            return self::printed('wp_print_styles');
        });

        $this->assertStringNotContainsString(self::LINK, $head);
    }

    #[Test]
    public function it_lets_a_theme_deregister_the_stylesheet_of_a_listing_rendered_after_the_head(): void
    {
        $body = $this->withFreshStyles(fn (): string => $this->afterTheHead(static function (): string {
            $stylesheet = new Stylesheet;
            $stylesheet->register();
            wp_deregister_style(Stylesheet::HANDLE);

            return self::printed($stylesheet->require(...));
        }));

        $this->assertStringNotContainsString(self::LINK, $body);
    }

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
