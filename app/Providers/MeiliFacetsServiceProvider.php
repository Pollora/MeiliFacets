<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\Facades\View;
use IntlException;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\DefaultTerms;
use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Contracts\TermScope;
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
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Listing\WordPressDefaultTerms;
use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Listing\WordPressTermScope;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Support\WooCommerce;
use Modules\MeiliFacets\View\CardSettings;
use Modules\MeiliFacets\View\ListingScript;
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
        $this->app->scoped(TermScope::class, WordPressTermScope::class);
        $this->app->scoped(DefaultTerms::class, WordPressDefaultTerms::class);
        // WordPress has not loaded its plugins here: `wc_get_product` does not exist yet, so the
        // WooCommerce guard belongs inside the closure, never around the binding.
        $this->app->bind(IndexAttributes::class, fn (): IndexAttributes => $this->indexAttributes());
        $this->app->bindIf(CardProjector::class, fn (): CardProjector => $this->defaultCard());
        $this->app->bind(FacetCounter::class, DisjunctiveFacetCounter::class);
        $this->app->bind(NameOrder::class, fn (): NameOrder => new NameOrder($this->collator()));
        // Unguarded: `ProductListing` is their only consumer, and it refuses itself without WooCommerce.
        $this->app->scopedIf(ProductFacets::class, WooCommerceFacets::class);
        $this->app->scopedIf(ProductSorts::class, WooCommerceSorts::class);

        $this->app->singleton(ListingRegistry::class);
        $this->app->scoped(CurrentListing::class);
        $this->app->scoped(Unavailable::class);
        $this->app->bind(UrlParameters::class, UrlParameters::fromConfig(...));
        $this->app->bind(CardSettings::class, fn (): CardSettings => new CardSettings(
            (int) config('meilifacets.card.eager', CardSettings::DEFAULT_EAGER)
        ));
        $this->app->scoped(SearchEngine::class, $this->searchEngine(...));
        $this->app->scoped(BrowserConnection::class, $this->browserConnection(...));
        $this->app->scoped(ListingScript::class);
        $this->app->scoped(EngineLimits::class, fn (): EngineLimits => new EngineLimits(
            (int) config('meilifacets.engine.reachable_hits', EngineLimits::DEFAULT_REACHABLE_HITS)
        ));
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

    /** The only place MeiliScout is reached from: one border with the plugin. */
    private function searchEngine(): SearchEngine
    {
        return new MeilisearchEngine(
            ClientFactory::getSearchClient(),
            (new PostIndexable)->getIndexName(),
        );
    }

    private function browserConnection(): BrowserConnection
    {
        return new BrowserConnection(
            (string) config('meilifacets.browser.url', ''),
            (string) config('meilifacets.browser.key', ''),
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

    /**
     * Read here rather than in `register()`: Polylang sets the language on a later
     * hook, and an empty locale collates as `en_US_POSIX`, not as the site's.
     */
    private function collator(): ?Collator
    {
        if (! class_exists(Collator::class)) {
            return null;
        }

        $locale = function_exists('get_locale') ? get_locale() : $this->app->getLocale();

        try {
            $collator = new Collator($locale);
        } catch (IntlException) {
            return null;
        }

        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);

        return $collator;
    }

    private function defaultCard(): CardProjector
    {
        $card = new DefaultCardProjector(
            (string) config('meilifacets.card.image_size', DefaultCardProjector::DEFAULT_IMAGE_SIZE)
        );

        return WooCommerce::isActive() ? new WooCommerceCardProjector($card) : $card;
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
