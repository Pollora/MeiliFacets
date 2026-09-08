<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\Facades\View;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use Modules\MeiliFacets\Discovery\ListingDiscovery;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Hook\Domain\Contracts\Action;

/** The module's entry point: it declares itself, then hands each layer its own provider. */
final class MeiliFacetsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'MeiliFacets';

    protected string $nameLower = 'meilifacets';

    /** @var list<class-string> */
    protected array $commands = [CheckParametersCommand::class];

    /** @var list<class-string> */
    private const array LAYERS = [
        IndexingServiceProvider::class,
        SearchServiceProvider::class,
        ListingServiceProvider::class,
        RenderingServiceProvider::class,
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

        $this->registerListings();
        $this->letTheThemeOverrideViews();
        $this->publishAssets();
    }

    private function publishAssets(): void
    {
        $this->publishes(
            [module_path($this->name, 'resources/assets') => public_path('modules/'.$this->nameLower)],
            $this->nameLower.'-assets',
        );
    }

    private function registerListings(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            $this->app->make(DiscoveryEngineInterface::class)
                ->addDiscovery('meilifacets_listings', $this->app->make(ListingDiscovery::class));
        }
    }

    /** nwidart builds its cascade from `config('view.paths')`, which Pollora fills with the theme only afterwards. */
    private function letTheThemeOverrideViews(): void
    {
        if (! $this->app->bound(Action::class)) {
            return;
        }

        $this->app->make(Action::class)->add('after_setup_theme', function (): void {
            $theme = get_stylesheet_directory().self::THEME_VIEWS;

            if (is_dir($theme)) {
                View::getFinder()->prependNamespace($this->nameLower, $theme);
            }
        });
    }
}
