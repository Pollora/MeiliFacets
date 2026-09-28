<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\View\ClientScript;
use Modules\MeiliFacets\View\ListingDescription;
use PHPUnit\Framework\Attributes\Test;
use Pollora\MeiliScout\Config\Config;
use Tests\TestCase;
use WP_Script_Modules;

final class ClientScriptTest extends TestCase
{
    private const string LISTING_DATA = 'script_module_data_'.ScriptModule::Listing->value;

    protected function tearDown(): void
    {
        remove_all_filters(self::LISTING_DATA);

        parent::tearDown();
    }

    #[Test]
    public function it_fetches_the_client_after_what_the_page_shows(): void
    {
        $this->assertStringContainsString('fetchpriority="low"', $this->printedModules($this->requireTheListing(...)));
    }

    #[Test]
    public function it_lets_a_project_choose_another_priority(): void
    {
        $highest = static fn (): string => 'high';
        add_filter(ClientScript::PRIORITY_FILTER, $highest);

        try {
            $this->assertStringContainsString(
                'fetchpriority="high"',
                $this->printedModules($this->requireTheListing(...))
            );
        } finally {
            remove_filter(ClientScript::PRIORITY_FILTER, $highest);
        }
    }

    #[Test]
    public function it_keeps_the_listing_script_out_of_the_scripts_wp_rocket_delays(): void
    {
        $this->assertTrue($this->isExcluded($this->moduleTag('modules/meilifacets/dist/listing.js')));
    }

    #[Test]
    public function it_leaves_another_module_script_to_wp_rocket(): void
    {
        $this->assertFalse($this->isExcluded($this->moduleTag('modules/wishlist/js/wishlist.js')));
    }

    /** The payload `ListingScript` published before it was shared with the search, key for key. */
    #[Test]
    public function it_publishes_the_listing_exactly_as_before_the_extraction(): void
    {
        $listing = $this->app->make(CurrentListing::class)->sole();

        $this->printedModules($this->requireTheListing(...));

        $this->assertSame(
            json_encode([
                'kept' => true,
                'connection' => ['url' => 'https://engine.test', 'key' => 'search', 'index' => 'products'],
                'listings' => [$listing->name() => $this->app->make(ListingDescription::class)->of($listing)],
            ]),
            json_encode(apply_filters(self::LISTING_DATA, ['kept' => true]))
        );
    }

    /** Assertions are booleans: a failure message would print the key it looked for. */
    #[Test]
    public function it_hands_the_browser_no_key_but_the_search_one(): void
    {
        $master = (string) Config::get('meili_key');
        $connection = $this->app->make(BrowserConnection::class);
        $script = new ClientScript($connection);
        $this->printedModules(static fn () => $script->require(ScriptModule::Listing, 'products', static fn (): array => []));

        $published = (array) apply_filters(self::LISTING_DATA, []);

        $this->assertNotSame('', $master, 'MeiliScout has no master key to compare with.');
        $this->assertFalse(in_array($master, $this->valuesOf($published), true));
        $this->assertTrue($published['connection']['key'] === $connection->key);
    }

    #[Test]
    public function it_reads_no_description_while_the_browser_has_no_engine_to_reach(): void
    {
        $script = new ClientScript(new BrowserConnection('', '', ''));

        $printed = $this->printedModules(fn () => $script->require(
            ScriptModule::Listing,
            'products',
            fn (): array => $this->fail('The description was built for nothing.'),
        ));

        $this->assertStringNotContainsString(ScriptModule::Listing->value, $printed);
    }

    #[Test]
    public function it_inscribes_no_bundle_the_module_has_not_published(): void
    {
        $this->assertFileDoesNotExist(
            public_path(ScriptModule::SiteSearch->source()),
            'The search bundle is built at step 4 of the site search: this test then has to pick another bundle.'
        );

        $printed = $this->printedModules(fn () => $this->script()->require(
            ScriptModule::SiteSearch,
            'search',
            static fn (): array => [],
        ));

        $this->assertStringNotContainsString(ScriptModule::SiteSearch->value, $printed);
        $this->assertFalse(has_filter('script_module_data_'.ScriptModule::SiteSearch->value));
        $this->assertFalse($this->isExcluded($this->moduleTag(ScriptModule::SiteSearch->source())));
    }

    /**
     * @param  array<mixed>  $published
     * @return list<mixed>
     */
    private function valuesOf(array $published): array
    {
        $values = [];
        array_walk_recursive($published, static function (mixed $value) use (&$values): void {
            $values[] = $value;
        });

        return $values;
    }

    private function requireTheListing(): void
    {
        $listing = $this->app->make(CurrentListing::class)->sole();
        $description = $this->app->make(ListingDescription::class);

        $this->script()->require(ScriptModule::Listing, $listing->name(), fn (): array => $description->of($listing));
    }

    private function script(): ClientScript
    {
        return new ClientScript(new BrowserConnection('https://engine.test', 'search', 'products'));
    }

    private function isExcluded(string $tag): bool
    {
        foreach ((array) apply_filters('rocket_delay_js_exclusions', []) as $pattern) {
            // WP Rocket escapes each exclusion, then matches it against the whole tag (`DelayJS\HTML`).
            $escaped = str_replace(['+', '?ver', '#'], ['\\+', '\\?ver', '\\#'], (string) $pattern);

            if (preg_match("#{$escaped}#i", $tag) === 1) {
                return true;
            }
        }

        return false;
    }

    private function printedModules(Closure $require): string
    {
        $shared = $GLOBALS['wp_script_modules'] ?? null;
        $GLOBALS['wp_script_modules'] = new WP_Script_Modules;

        try {
            $require();

            ob_start();
            wp_script_modules()->print_enqueued_script_modules();

            return (string) ob_get_clean();
        } finally {
            $GLOBALS['wp_script_modules'] = $shared;
        }
    }

    private function moduleTag(string $path): string
    {
        return wp_get_script_tag([
            'type' => 'module',
            'src' => asset($path).'?ver=1',
            'id' => ScriptModule::Listing->value.'-js-module',
        ]);
    }
}
