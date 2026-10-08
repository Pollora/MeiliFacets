<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\Facades\View;
use Modules\MeiliFacets\Console\CheckAssetsCommand;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

/** The module's entry point: it declares itself, then hands each layer its own provider. */
final class MeiliFacetsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'MeiliFacets';

    protected string $nameLower = 'meilifacets';

    /** @var list<class-string> */
    protected array $commands = [CheckAssetsCommand::class, CheckParametersCommand::class];

    /** @var list<class-string> */
    private const array LAYERS = [
        IndexingServiceProvider::class,
        SearchServiceProvider::class,
        ListingServiceProvider::class,
        RenderingServiceProvider::class,
        SiteSearchServiceProvider::class,
    ];

    private const string THEME_VIEWS = '/resources/views/modules/meilifacets';

    public function register(): void
    {
        parent::register();

        foreach (self::LAYERS as $layer) {
            $this->app->register($layer);
        }
    }

    public function boot(): void
    {
        parent::boot();

        $this->letTheThemeOverrideViews();
        $this->publishAssets();
        $this->publishStarterConfig();
    }

    private function publishAssets(): void
    {
        $this->publishes(
            [module_path($this->name, 'resources/assets') => public_path('modules/'.$this->nameLower)],
            $this->nameLower.'-assets',
        );
    }

    /**
     * A commented starting point, not `config/config.php`: nwidart merges that one
     * with the module last, so anything declared there could never be overridden.
     */
    private function publishStarterConfig(): void
    {
        $this->publishes(
            [module_path($this->name, 'config/'.$this->nameLower.'.php.stub') => config_path($this->nameLower.'.php')],
            $this->nameLower.'-config',
        );
    }

    /**
     * nwidart builds its cascade from `config('view.paths')`, which Pollora fills with the theme only afterwards.
     * Pollora renamed its hook contract within 13.x (`Domain\Contracts` to `Domain\Contract`): WordPress's own
     * function holds across the versions the module supports.
     */
    private function letTheThemeOverrideViews(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('after_setup_theme', function (): void {
            $theme = get_stylesheet_directory().self::THEME_VIEWS;

            if (is_dir($theme)) {
                View::getFinder()->prependNamespace($this->nameLower, $theme);
            }
        });
    }
}
