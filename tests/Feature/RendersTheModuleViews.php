<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\View;

/**
 * A host theme overrides the module's views (`resources/views/modules/meilifacets`):
 * a test of the module's own markup reads the module's copy, whatever theme is active.
 */
trait RendersTheModuleViews
{
    private const string VIEW_NAMESPACE = 'meilifacets';

    private const string MODULE_VIEWS = __DIR__.'/../../resources/views';

    /** @var list<string> */
    private array $viewPaths = [];

    private function renderTheModuleViews(): void
    {
        $this->viewPaths = View::getFinder()->getHints()[self::VIEW_NAMESPACE] ?? [];

        View::getFinder()->replaceNamespace(self::VIEW_NAMESPACE, [(string) realpath(self::MODULE_VIEWS)]);
        View::getFinder()->flush();
    }

    /** The finder is shared: the theme's copies would stay out of every later test of the application. */
    private function restoreTheThemeViews(): void
    {
        View::getFinder()->replaceNamespace(self::VIEW_NAMESPACE, $this->viewPaths);
        View::getFinder()->flush();
    }
}
