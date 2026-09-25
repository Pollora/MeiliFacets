<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Pollora\Attributes\Action;

final class Stylesheet
{
    public const string HANDLE = 'meilifacets';

    /** Where `php artisan module:publish MeiliFacets` puts the module's assets. */
    private const string PATH = 'modules/meilifacets/css/meilifacets.css';

    #[Action('wp_enqueue_scripts')]
    public function register(): void
    {
        $file = public_path(self::PATH);

        if (! is_file($file)) {
            return;
        }

        wp_register_style(self::HANDLE, asset(self::PATH), [], (string) filemtime($file));
    }

    /** A Blade layout renders its sections before its `<head>`, so a listing asks before `wp_head`. */
    public function require(): void
    {
        if (did_action('wp_head') > 0) {
            wp_print_styles(self::HANDLE);

            return;
        }

        wp_enqueue_style(self::HANDLE);
    }
}
