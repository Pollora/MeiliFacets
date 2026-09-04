<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\Facades\View;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Discovery\ListingDiscovery;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Indexing\ConfiguredIndexAttributes;
use Modules\MeiliFacets\Indexing\DefaultCardProjector;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerceCardProjector;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Support\WooCommerce;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Hook\Domain\Contracts\Action;
use Pollora\MeiliScout\Indexables\PostIndexable;
use Pollora\MeiliScout\Services\ClientFactory;

final class MeiliFacetsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'MeiliFacets';

    protected string $nameLower = 'meilifacets';

    /** @var list<class-string> */
    protected array $commands = [CheckParametersCommand::class];

    private const string THEME_VIEWS = '/resources/views/modules/meilifacets';

    public function register(): void
    {
        parent::register();

        $this->app->bind(TermHierarchy::class, WordPressTermHierarchy::class);
        $this->app->bind(TermLabels::class, WordPressTermLabels::class);
        $this->app->bind(IndexAttributes::class, fn (): IndexAttributes => $this->indexAttributes());
        $this->app->bindIf(CardProjector::class, fn (): CardProjector => $this->defaultCard());
        $this->app->bind(FacetCounter::class, DisjunctiveFacetCounter::class);

        $this->app->singleton(ListingRegistry::class);
        $this->app->scoped(CurrentListing::class);
        $this->app->scoped(Unavailable::class);
        $this->app->bind(UrlParameters::class, UrlParameters::fromConfig(...));
        $this->app->scoped(SearchEngine::class, $this->searchEngine(...));
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerListings();
        $this->letTheThemeOverrideViews();
    }

    /**
     * The only place MeiliScout is reached from: the client and the index name
     * both come from it, so the module holds one border with the plugin.
     */
    private function searchEngine(): SearchEngine
    {
        return new MeilisearchEngine(
            ClientFactory::getSearchClient(),
            (new PostIndexable)->getIndexName(),
        );
    }

    /**
     * The two methods below are the only place a plugin reaches the index.
     */
    private function indexAttributes(): IndexAttributes
    {
        $plugin = WooCommerce::isActive() ? new WooCommerceIndexAttributes : new EmptyIndexAttributes;

        return new ConfiguredIndexAttributes($plugin, (array) config('meilifacets.displayed_attributes', []));
    }

    private function defaultCard(): CardProjector
    {
        // A key declared in the module config would win over the project one, and
        // config:cache would drop it: overridable settings are read with a default.
        $card = new DefaultCardProjector(
            (string) config('meilifacets.card.image_size', DefaultCardProjector::DEFAULT_IMAGE_SIZE)
        );

        return WooCommerce::isActive() ? new WooCommerceCardProjector($card) : $card;
    }

    private function registerListings(): void
    {
        $registry = $this->app->make(ListingRegistry::class);

        if (WooCommerce::isActive()) {
            $registry->add(new ProductListing);
        }

        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            $this->app->make(DiscoveryEngineInterface::class)
                ->addDiscovery('meilifacets_listings', $this->app->make(ListingDiscovery::class));
        }
    }

    /**
     * nwidart builds its cascade from config('view.paths'), which Pollora fills
     * with the theme only afterwards: the theme path is added back here.
     */
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
