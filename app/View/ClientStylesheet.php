<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\Stylesheet;
use Pollora\Attributes\Action;

/** Registers every published stylesheet, and prints the one a root asks for where it is still in time. */
final class ClientStylesheet
{
    #[Action('wp_enqueue_scripts')]
    public function register(): void
    {
        foreach (array_filter(Stylesheet::cases(), $this->isPublished(...)) as $stylesheet) {
            $this->registerHandle($stylesheet);
        }
    }

    /** A Blade layout renders its sections before its `<head>`, so a root asks before `wp_head`. */
    public function require(Stylesheet $stylesheet): void
    {
        if (did_action('wp_head') > 0) {
            wp_print_styles($stylesheet->value);

            return;
        }

        wp_enqueue_style($stylesheet->value);
    }

    private function registerHandle(Stylesheet $stylesheet): void
    {
        $source = $stylesheet->source();

        wp_register_style($stylesheet->value, asset($source), [], (string) filemtime(public_path($source)));
    }

    private function isPublished(Stylesheet $stylesheet): bool
    {
        return is_file(public_path($stylesheet->source()));
    }
}
