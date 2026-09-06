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
    public function enqueue(): void
    {
        $file = public_path(self::PATH);

        if (! is_file($file)) {
            return;
        }

        wp_enqueue_style(self::HANDLE, asset(self::PATH), [], (string) filemtime($file));
    }
}
