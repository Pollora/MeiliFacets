<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\View\ListingDescription;
use Modules\MeiliFacets\View\ListingScript;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Script_Modules;

final class ListingScriptTest extends TestCase
{
    #[Test]
    public function it_fetches_the_client_after_what_the_page_shows(): void
    {
        $this->assertStringContainsString('fetchpriority="low"', $this->printedModules());
    }

    #[Test]
    public function it_lets_a_project_choose_another_priority(): void
    {
        $highest = static fn (): string => 'high';
        add_filter(ListingScript::PRIORITY_FILTER, $highest);

        try {
            $this->assertStringContainsString('fetchpriority="high"', $this->printedModules());
        } finally {
            remove_filter(ListingScript::PRIORITY_FILTER, $highest);
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

    private function printedModules(): string
    {
        $shared = $GLOBALS['wp_script_modules'] ?? null;
        $GLOBALS['wp_script_modules'] = new WP_Script_Modules;

        try {
            $script = new ListingScript(
                new BrowserConnection('https://engine.test', 'search', 'products'),
                $this->app->make(ListingDescription::class),
            );
            $script->require($this->app->make(CurrentListing::class)->sole());

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
            'id' => ListingScript::MODULE.'-js-module',
        ]);
    }
}
