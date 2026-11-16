<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\View\ClientScript;
use Modules\MeiliFacets\View\ListingDescription;
use Pollora\MeiliScout\Config\Config;
use Tests\TestCase;
use WP_Script_Modules;

trait ClientScriptHelpers
{
    private const string LISTING_DATA = 'script_module_data_'.ScriptModule::Listing->value;

    private const string SEARCH_DATA = 'script_module_data_'.ScriptModule::SiteSearch->value;

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
        $listing = $this->app->make(CurrentListing::class)->named(ProductListing::NAME);
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

uses(TestCase::class, ClientScriptHelpers::class);

afterEach(function (): void {
    remove_all_filters(self::LISTING_DATA);
});

it('fetches the client after what the page shows', function (): void {
    expect($this->printedModules($this->requireTheListing(...)))->toContain('fetchpriority="low"');
});

it('lets a project choose another priority', function (): void {
    $highest = static fn (): string => 'high';
    add_filter(ClientScript::PRIORITY_FILTER, $highest);

    try {
        expect($this->printedModules($this->requireTheListing(...)))->toContain('fetchpriority="high"');
    } finally {
        remove_filter(ClientScript::PRIORITY_FILTER, $highest);
    }
});

it('keeps the listing script out of the scripts wp rocket delays', function (): void {
    expect($this->isExcluded($this->moduleTag('modules/meilifacets/dist/listing.js')))->toBeTrue();
});

it('leaves another module script to wp rocket', function (): void {
    expect($this->isExcluded($this->moduleTag('modules/wishlist/js/wishlist.js')))->toBeFalse();
});

/** The payload `ListingScript` published before it was shared with the search, key for key. */
it('publishes the listing exactly as before the extraction', function (): void {
    $listing = $this->app->make(CurrentListing::class)->named(ProductListing::NAME);

    $this->printedModules($this->requireTheListing(...));

    // Kept as `assertSame`: the expected side is built before the filter runs, as the original did.
    $this->assertSame(
        json_encode([
            'kept' => true,
            'connection' => ['url' => 'https://engine.test', 'key' => 'search', 'index' => 'products'],
            'listings' => [$listing->name() => $this->app->make(ListingDescription::class)->of($listing)],
        ]),
        json_encode(apply_filters(self::LISTING_DATA, ['kept' => true]))
    );
});

/** Assertions are booleans: a failure message would print the key it looked for. */
it('hands the browser no key but the search one', function (): void {
    $master = (string) Config::get('meili_key');
    $connection = $this->app->make(BrowserConnection::class);
    $script = new ClientScript($connection);
    $this->printedModules(static fn () => $script->require(ScriptModule::Listing, 'products', static fn (): array => []));

    $published = (array) apply_filters(self::LISTING_DATA, []);

    $this->assertNotSame('', $master, 'MeiliScout has no master key to compare with.');
    expect(in_array($master, $this->valuesOf($published), true))->toBeFalse();
    expect($published['connection']['key'] === $connection->key)->toBeTrue();
});

it('reads no description while the browser has no engine to reach', function (): void {
    $script = new ClientScript(new BrowserConnection('', '', ''));

    $printed = $this->printedModules(fn () => $script->require(
        ScriptModule::Listing,
        'products',
        fn (): array => $this->fail('The description was built for nothing.'),
    ));

    expect($printed)->not->toContain(ScriptModule::Listing->value);
});

it('inscribes the search loader like the listing and files its roots under their key', function (): void {
    $printed = $this->printedModules(fn () => $this->script()->require(
        ScriptModule::SiteSearch,
        'header',
        static fn (): array => ['name' => 'header'],
    ));

    try {
        expect($printed)->toContain('src="'.asset(ScriptModule::SiteSearch->source()).'?ver=');
        expect($printed)->toContain('fetchpriority="low"');
        expect(apply_filters(self::SEARCH_DATA, [])['searches'] ?? null)->toBe(['header' => ['name' => 'header']]);
    } finally {
        remove_all_filters(self::SEARCH_DATA);
    }
});

/** The client is imported by the loader, never printed: Delay JS has no tag of it to hold back. */
it('keeps the search loader out of the scripts wp rocket delays', function (): void {
    expect($this->isExcluded($this->moduleTag(ScriptModule::SiteSearch->source())))->toBeTrue();
});

it('inscribes no bundle the module has not published', function (): void {
    $published = public_path();
    $this->app->usePublicPath(sys_get_temp_dir().'/meilifacets-unpublished');

    try {
        $printed = $this->printedModules(fn () => $this->script()->require(
            ScriptModule::SiteSearch,
            'search',
            static fn (): array => [],
        ));

        expect($printed)->not->toContain(ScriptModule::SiteSearch->value);
        expect(has_filter(self::SEARCH_DATA))->toBeFalse();
        expect($this->isExcluded($this->moduleTag(ScriptModule::SiteSearch->source())))->toBeFalse();
    } finally {
        $this->app->usePublicPath($published);
    }
});
