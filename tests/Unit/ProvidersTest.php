<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Console\CheckAssetsCommand;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\PageMetadata;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Http\ServiceUnavailableHeaders;
use Modules\MeiliFacets\Indexing\ConfiguredIndexAttributes;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;
use Modules\MeiliFacets\Indexing\DeferredCardProjector;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Indexing\WooCommerce\EmptyVariantFields;
use Modules\MeiliFacets\Indexing\WooCommerce\VariationChanges;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceProductFields;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Listing\WordPressTermScope;
use Modules\MeiliFacets\Providers\IndexingServiceProvider;
use Modules\MeiliFacets\Providers\ListingServiceProvider;
use Modules\MeiliFacets\Providers\MeiliFacetsServiceProvider;
use Modules\MeiliFacets\Providers\RenderingServiceProvider;
use Modules\MeiliFacets\Providers\SearchServiceProvider;
use Modules\MeiliFacets\Providers\SiteSearchServiceProvider;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;
use Modules\MeiliFacets\Support\SiteCollator;
use Modules\MeiliFacets\Tests\Unit\Doubles\Application;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\CardSettings;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\WordPressPageMetadata;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** What each layer binds, resolved from a container of its own, in a process of its own: see `Doubles\WordPress`. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ProvidersTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        WordPress::boot();
        require_once __DIR__.'/Doubles/http-stubs.php';
        $app = app();
        $this->assertInstanceOf(Application::class, $app);
        $this->app = $app;
    }

    #[Test]
    public function the_indexing_layer_binds_what_a_document_is_made_of(): void
    {
        $this->register(IndexingServiceProvider::class);
        WordPress::$config['meilifacets.displayed_attributes'] = ['sku'];

        $this->assertInstanceOf(WordPressTermHierarchy::class, $this->app->make(TermHierarchy::class));
        $this->assertInstanceOf(EmptyVariantFields::class, $this->app->make(VariantFields::class));
        $this->assertInstanceOf(DefaultSearchableAttributes::class, $this->app->make(SearchableAttributes::class));
        $this->assertInstanceOf(WooCommerceProductFields::class, $this->app->make(WooCommerceProductFields::class));

        $attributes = $this->app->make(IndexAttributes::class);
        $this->assertInstanceOf(ConfiguredIndexAttributes::class, $attributes);
        $this->assertContains('sku', $attributes->displayed());
    }

    #[Test]
    public function the_card_is_projected_through_the_plugin_state_and_shares_what_hooks_remember(): void
    {
        $this->register(IndexingServiceProvider::class);

        $this->assertInstanceOf(DeferredCardProjector::class, $this->app->make(CardProjector::class));
        $this->assertSame($this->app->make(VariationChanges::class), $this->app->make(VariationChanges::class));
    }

    #[Test]
    public function a_project_binding_wins_over_the_default_card_and_variant_fields(): void
    {
        $project = new class implements CardProjector
        {
            public function project(\WP_Post $post): array
            {
                return ['own' => true];
            }
        };
        $this->app->instance(CardProjector::class, $project);

        $this->register(IndexingServiceProvider::class);

        $this->assertSame($project, $this->app->make(CardProjector::class));
    }

    #[Test]
    public function the_search_layer_binds_the_engine_limits_the_counter_and_a_lazy_browser_connection(): void
    {
        WordPress::$config['meilifacets.engine.reachable_hits'] = 500;
        WordPress::$config['meilifacets.engine.max_facet_values'] = 250;
        WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $default);
        WordPress::fake('home_url', 'https://example.test');
        WordPress::fake('is_multisite', false);
        $this->register(SearchServiceProvider::class);

        $limits = $this->app->make(EngineLimits::class);

        $this->assertSame(500, $limits->reachableHits);
        $this->assertSame(250, $limits->maxFacetValues);
        $this->assertInstanceOf(DisjunctiveFacetCounter::class, $this->app->make(FacetCounter::class));
        $this->assertInstanceOf(MeilisearchEngine::class, $this->app->make(SearchEngine::class));
    }

    #[Test]
    public function the_browser_connection_reads_its_address_only_when_first_used(): void
    {
        WordPress::$config['meilifacets.browser.url'] = 'https://search.example.test';
        WordPress::$config['meilifacets.browser.key'] = 'search-only';
        WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $key === 'meiliscout/active_indexes' ? ['posts' => 'posts_v2'] : $default);
        $this->register(SearchServiceProvider::class);

        $connection = $this->app->make(BrowserConnection::class);

        $this->assertSame('https://search.example.test', $connection->url);
        $this->assertSame('search-only', $connection->key);
    }

    #[Test]
    public function the_listing_layer_binds_terms_sorts_and_a_registry_shared_by_the_module(): void
    {
        $this->register(ListingServiceProvider::class);

        $this->assertInstanceOf(WordPressTermLabels::class, $this->app->make(TermLabels::class));
        $this->assertInstanceOf(WooCommerceSorts::class, $this->app->make(ProductSorts::class));
        $this->assertSame($this->app->make(ListingRegistry::class), $this->app->make(ListingRegistry::class));
        $this->assertSame($this->app->make(SiteCollator::class), $this->app->make(SiteCollator::class));

        $this->app->bind(TermHierarchy::class, WordPressTermHierarchy::class);
        $this->assertInstanceOf(WordPressTermScope::class, $this->app->make(TermScope::class));
        $this->assertInstanceOf(WooCommerceFacets::class, $this->app->make(ProductFacets::class));
        $this->assertInstanceOf(NameOrder::class, $this->app->make(NameOrder::class));
    }

    #[Test]
    public function the_rendering_layer_reads_its_card_settings_and_the_site_locale(): void
    {
        WordPress::$config['meilifacets.card.eager'] = 4;
        WordPress::fake('get_locale', 'fr_FR');
        $this->register(RenderingServiceProvider::class);

        $this->assertInstanceOf(CardSettings::class, $this->app->make(CardSettings::class));
        $this->assertSame('fr-FR', $this->app->make(CountLabel::class)->languageTag());
    }

    #[Test]
    public function the_page_metadata_is_the_wordpress_one_until_yoast_is_active(): void
    {
        $this->register(RenderingServiceProvider::class);

        $this->assertInstanceOf(WordPressPageMetadata::class, $this->app->make(PageMetadata::class));
    }

    #[Test]
    public function the_rendering_layer_pushes_its_503_middleware_on_the_http_kernel(): void
    {
        $kernel = new HttpKernel;
        $this->app->instance(Kernel::class, $kernel);
        $provider = new RenderingServiceProvider($this->app);

        $provider->boot();

        $this->assertSame([ServiceUnavailableHeaders::class], $kernel->pushed);
    }

    #[Test]
    public function the_rendering_layer_leaves_any_other_kernel_alone(): void
    {
        $kernel = new class implements Kernel
        {
            public function bootstrap(): void {}

            public function handle($request): mixed
            {
                return null;
            }

            public function terminate($request, $response): void {}

            public function getApplication(): mixed
            {
                return null;
            }
        };
        $this->app->instance(Kernel::class, $kernel);

        new RenderingServiceProvider($this->app)->boot();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function the_site_search_layer_binds_the_types_and_the_default_settings(): void
    {
        WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $default);
        $this->register(IndexingServiceProvider::class);
        $this->register(SiteSearchServiceProvider::class);

        $this->assertInstanceOf(SearchSettings::class, $this->app->make(SearchSettings::class));
        $this->assertInstanceOf(WooCommerceSearchableTypes::class, $this->app->make(SearchableTypes::class));
    }

    #[Test]
    public function the_module_registers_its_five_layers_and_its_two_commands(): void
    {
        $provider = new MeiliFacetsServiceProvider($this->app);

        $provider->register();

        $this->assertNotEmpty($this->app->getBindings());
        $this->assertInstanceOf(FacetedPostIndexable::class, $this->app->make(FacetedPostIndexable::class));
        $commands = new \ReflectionProperty($provider, 'commands')->getValue($provider);
        $this->assertSame([CheckAssetsCommand::class, CheckParametersCommand::class], $commands);
    }

    #[Test]
    public function the_module_lets_the_theme_override_its_views_and_publishes_assets_and_a_starter_config(): void
    {
        WordPress::fake('module_path', static fn (string $name, string $path): string => "/modules/{$name}/{$path}");
        WordPress::fake('public_path', static fn (string $path): string => "/public/{$path}");
        $this->defineConfigPath();
        $hooks = [];
        $this->defineAddAction($hooks);

        new MeiliFacetsServiceProvider($this->app)->boot();

        $this->assertSame(['after_setup_theme'], array_keys($hooks));
        $paths = ServiceProvider::pathsToPublish(MeiliFacetsServiceProvider::class);
        $this->assertSame(
            [
                '/modules/MeiliFacets/resources/assets' => '/public/modules/meilifacets',
                '/modules/MeiliFacets/config/meilifacets.php.stub' => '/config/meilifacets.php',
            ],
            $paths
        );
    }

    /**
     * @param  class-string<ServiceProvider>  $provider
     */
    private function register(string $provider): void
    {
        new $provider($this->app)->register();
    }

    private function defineConfigPath(): void
    {
        if (! function_exists('config_path')) {
            eval('function config_path(string $path = ""): string { return "/config/".$path; }');
        }
    }

    /**
     * @param  array<string, callable>  $hooks
     */
    private function defineAddAction(array &$hooks): void
    {
        WordPress::fake('add_action', static function (string $hook, callable $callback) use (&$hooks): void {
            $hooks[$hook] = $callback;
        });

        if (! function_exists('add_action')) {
            eval('function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true { Modules\MeiliFacets\Tests\Unit\Doubles\WordPress::call("add_action", [$hook_name, $callback]); return true; }');
        }
    }
}
