<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Enums\Stylesheet;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Listing\SearchableType;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\BuildsResolvedListings;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\ClientScript;
use Modules\MeiliFacets\View\ClientStylesheet;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\DocumentTitle;
use Modules\MeiliFacets\View\ListingRobots;
use Modules\MeiliFacets\View\Preconnect;
use Modules\MeiliFacets\View\SiteSearchDescription;
use Modules\MeiliFacets\View\WordPressPageMetadata;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Reads WordPress and Laravel helpers, so each test gets a process of its own: see `Doubles\WordPress`. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ViewRuntimeTest extends TestCase
{
    use BuildsResolvedListings;

    private string $public = '';

    protected function setUp(): void
    {
        WordPress::boot();
        $this->public = sys_get_temp_dir().'/meilifacets-view-'.bin2hex(random_bytes(6));
        mkdir($this->public.'/modules/meilifacets/dist', recursive: true);
        mkdir($this->public.'/modules/meilifacets/css', recursive: true);
        WordPress::fake('public_path', fn (string $path): string => $this->public.'/'.$path);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->public.'/modules/meilifacets/{dist,css}/*', GLOB_BRACE) ?: [] as $file) {
            new Filesystem()->delete($file);
        }

        foreach (['/modules/meilifacets/dist', '/modules/meilifacets/css', '/modules/meilifacets', '/modules', ''] as $folder) {
            @rmdir($this->public.$folder);
        }
    }

    #[Test]
    public function it_warms_the_engine_origin_on_a_listing_page_only(): void
    {
        $page = new ListingPage;
        $preconnect = new Preconnect(new BrowserConnection('https://search.example.test:7700/path', 'key', 'posts'), $page);

        ob_start();
        $preconnect->warmTheEngineOrigin();
        $this->assertSame('', ob_get_clean());

        $page->markAsCurrent($this->resolved());
        ob_start();
        $preconnect->warmTheEngineOrigin();

        $this->assertSame('<link rel="preconnect" href="https://search.example.test:7700" crossorigin>'."\n", ob_get_clean());
    }

    #[Test]
    public function it_warms_nothing_while_the_browser_has_no_engine_to_reach(): void
    {
        $page = new ListingPage;
        $page->markAsCurrent($this->resolved());
        $preconnect = new Preconnect(new BrowserConnection('', '', ''), $page);

        ob_start();
        $preconnect->warmTheEngineOrigin();

        $this->assertSame('', ob_get_clean());
        $this->assertSame('', $preconnect->origin());
    }

    #[Test]
    public function it_loads_no_bundle_without_a_connection_or_a_published_file(): void
    {
        $described = false;
        $description = function () use (&$described): array {
            $described = true;

            return [];
        };

        new ClientScript(new BrowserConnection('', '', ''))->require(ScriptModule::Listing, 'shop', $description);
        new ClientScript(new BrowserConnection('https://e.test', 'k', 'posts'))->require(ScriptModule::Listing, 'shop', $description);

        $this->assertFalse($described);
        $this->assertArrayNotHasKey('wp_enqueue_script_module', WordPress::$calls);
    }

    #[Test]
    public function it_enqueues_a_bundle_once_and_hands_it_one_description_per_root(): void
    {
        file_put_contents($this->public.'/'.ScriptModule::Listing->source(), 'export {}');
        WordPress::fake('apply_filters', static fn (string $hook, string $value, string $module): string => $hook === ClientScript::PRIORITY_FILTER ? 'high' : $value);
        $filters = [];
        WordPress::fake('add_filter', static function (string $hook, Closure $callback) use (&$filters): void {
            $filters[$hook] = $callback;
        });
        $script = new ClientScript(new BrowserConnection('https://e.test', 'k', 'posts'));

        $script->require(ScriptModule::Listing, 'shop', static fn (): array => ['name' => 'shop']);
        $script->require(ScriptModule::Listing, 'sale', static fn (): array => ['name' => 'sale']);

        $this->assertSame(1, WordPress::$calls['wp_enqueue_script_module']);
        [, $enqueued] = array_values(array_filter(WordPress::$log, static fn (array $call): bool => $call[0] === 'wp_enqueue_script_module'))[0];
        $this->assertSame('@meilifacets/listing', $enqueued[0]);
        $this->assertSame('https://example.test/modules/meilifacets/dist/listing.js', $enqueued[1]);
        $this->assertSame(['fetchpriority' => 'high'], $enqueued[4]);

        $this->assertSame(
            [
                'seen' => true,
                'connection' => ['url' => 'https://e.test', 'key' => 'k', 'index' => 'posts'],
                'listings' => ['shop' => ['name' => 'shop'], 'sale' => ['name' => 'sale']],
            ],
            $filters['script_module_data_@meilifacets/listing'](['seen' => true])
        );
    }

    #[Test]
    public function it_excludes_the_published_bundles_from_delayed_scripts(): void
    {
        file_put_contents($this->public.'/'.ScriptModule::SiteSearch->source(), 'export {}');

        $this->assertSame(
            ['other.js', 'modules/meilifacets/dist/site-search.js'],
            new ClientScript(new BrowserConnection('', '', ''))->excludeFromDelayedScripts(['other.js'])
        );
    }

    #[Test]
    public function it_registers_each_published_stylesheet_and_no_other(): void
    {
        file_put_contents($this->public.'/'.Stylesheet::Listing->source(), 'a{}');

        new ClientStylesheet()->register();

        $this->assertSame(1, WordPress::$calls['wp_register_style']);
        $registered = array_values(array_filter(WordPress::$log, static fn (array $call): bool => $call[0] === 'wp_register_style'))[0][1];
        $this->assertSame('meilifacets', $registered[0]);
        $this->assertSame('https://example.test/modules/meilifacets/css/meilifacets.css', $registered[1]);
    }

    #[Test]
    public function it_enqueues_a_stylesheet_before_the_head_and_prints_it_after(): void
    {
        $styles = new ClientStylesheet;

        $styles->require(Stylesheet::Listing);
        $this->assertSame(1, WordPress::$calls['wp_enqueue_style']);
        $this->assertArrayNotHasKey('wp_print_styles', WordPress::$calls);

        WordPress::fake('did_action', 1);
        $styles->require(Stylesheet::SiteSearch);
        $this->assertSame(['meilifacets-site-search'], WordPress::$log[array_key_last(WordPress::$log)][1]);
        $this->assertSame(1, WordPress::$calls['wp_print_styles']);
    }

    #[Test]
    public function it_renders_a_document_title_with_a_filter_held_only_while_it_does(): void
    {
        WordPress::fake('wp_get_document_title', 'Shop &amp; Co &#8211; Page 2');
        $filter = static fn (array $parts): array => $parts;

        $title = new DocumentTitle()->renderedWith('document_title_parts', $filter);

        $this->assertSame('Shop & Co – Page 2', $title);
        $this->assertSame(['add_filter', 'wp_get_document_title', 'remove_filter'], array_column(WordPress::$log, 0));
        $this->assertSame(PHP_INT_MAX, WordPress::$log[0][1][2]);
    }

    #[Test]
    public function it_removes_the_title_filter_even_when_wordpress_raises(): void
    {
        WordPress::fake('wp_get_document_title', static fn (): never => throw new \RuntimeException('boom'));

        try {
            new DocumentTitle()->renderedWith('document_title_parts', static fn (array $parts): array => $parts);
            $this->fail('The failure was swallowed.');
        } catch (\RuntimeException) {
            $this->assertSame(1, WordPress::$calls['remove_filter']);
        }
    }

    #[Test]
    public function it_tells_the_titles_of_the_first_and_later_pages_apart(): void
    {
        WordPress::fake('has_filter', false);
        $filter = null;
        WordPress::fake('add_filter', static function (string $hook, Closure $callback) use (&$filter): void {
            $filter = $callback;
        });
        WordPress::fake('wp_get_document_title', static function () use (&$filter): string {
            $parts = $filter(['title' => 'Shop', 'page' => 'Page 9', 'site' => 'Store']);

            return implode(' | ', array_filter($parts));
        });

        $titles = new WordPressPageMetadata(new DocumentTitle)->titles();

        $this->assertSame(['first' => 'Shop | Store', 'paged' => 'Shop | Page :page | Store'], $titles);
    }

    #[Test]
    public function it_cannot_tell_the_titles_when_a_plugin_writes_them(): void
    {
        WordPress::fake('has_filter', 10);

        $this->assertNull(new WordPressPageMetadata(new DocumentTitle)->titles());
    }

    #[Test]
    public function it_reads_the_canonical_wordpress_prints_on_a_singular_page(): void
    {
        WordPress::fake('is_singular', true);
        WordPress::fake('wp_get_canonical_url', 'https://example.test/shop/');
        $metadata = new WordPressPageMetadata(new DocumentTitle);

        $this->assertSame(['first' => 'https://example.test/shop/', 'paged' => 'https://example.test/shop/'], $metadata->canonicals());

        WordPress::fake('wp_get_canonical_url', false);
        $this->assertNull($metadata->canonicals());

        WordPress::fake('is_singular', false);
        $this->assertNull($metadata->canonicals());
    }

    #[Test]
    public function it_has_no_robots_to_describe_when_wordpress_prints_none(): void
    {
        WordPress::fake('has_action', false);

        $this->assertNull($this->robots()->byView());
    }

    #[Test]
    public function it_reads_the_robots_of_the_bare_view_and_of_any_other_through_the_printed_tag(): void
    {
        $held = [];
        WordPress::fake('add_filter', static function (string $hook, Closure $callback, int $priority) use (&$held): void {
            $held[$hook] = $callback;
        });
        WordPress::fake('remove_filter', static function (string $hook) use (&$held): void {
            unset($held[$hook]);
        });
        WordPress::fake('wp_robots', static function () use (&$held): void {
            $secondary = isset($held['wp_robots']);
            echo $secondary ? "<meta name='robots' content='noindex, follow, max-image-preview:large' />" : '';
        });

        $this->assertSame(
            ['bare' => '', 'secondary' => 'noindex, follow, max-image-preview:large'],
            $this->robots()->byView()
        );
        $this->assertSame([], $held);
    }

    #[Test]
    public function it_describes_a_search_root_for_the_browser(): void
    {
        $root = new SearchRoot('search', new SearchSettings(3, 200, 5), [
            'product' => new SearchableType('product', 'Products', 'See all', ['post_type = "product"'], ['post_title'], 'card', '/shop/'),
        ]);
        $description = new SiteSearchDescription(
            new CountLabel(static fn (): string => 'en_US'),
            new Preconnect(new BrowserConnection('https://e.test:7700', 'k', 'posts'), new ListingPage),
            new UrlParameters([], ['q' => 'term']),
        );

        $this->assertSame([
            'name' => 'search',
            'minChars' => 3,
            'delay' => 200,
            'limit' => 5,
            'types' => [[
                'postType' => 'product',
                'heading' => 'Products',
                'seeAllLabel' => 'See all',
                'baseFilter' => ['post_type = "product"'],
                'searchOn' => ['post_title'],
                'archive' => '/shop/',
            ]],
            'countPattern' => ':count result|:count results',
            'sectionPattern' => ':heading: :count',
            'locale' => 'en-US',
            'preconnect' => 'https://e.test:7700',
            'seeAllParameter' => 'term',
        ], $description->of($root));
    }

    private function robots(): ListingRobots
    {
        return new ListingRobots(new IndexingPolicy(new UrlParameters, new ListingPage));
    }
}
