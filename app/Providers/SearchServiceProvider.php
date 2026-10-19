<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use Pollora\MeiliScout\Services\ClientFactory;
use Pollora\MeiliScout\Services\IndexNames;

/** How a search is sent, and to which of the engine's two addresses. */
final class SearchServiceProvider extends ServiceProvider
{
    private const string POST_INDEX_KEY = 'posts';

    public function register(): void
    {
        $this->app->bindIf(FacetCounter::class, DisjunctiveFacetCounter::class);
        $this->app->scoped(SearchEngine::class, $this->engine(...));
        $this->app->scoped(BrowserConnection::class, $this->browser(...));
        $this->app->scoped(EngineLimits::class, fn (): EngineLimits => new EngineLimits(
            (int) config('meilifacets.engine.reachable_hits', EngineLimits::DEFAULT_REACHABLE_HITS),
            (int) config('meilifacets.engine.max_facet_values', EngineLimits::DEFAULT_MAX_FACET_VALUES),
        ));
    }

    /** The only place MeiliScout is reached from: one border with the plugin. */
    private function engine(): SearchEngine
    {
        return new MeilisearchEngine(
            ClientFactory::getSearchClient(),
            $this->searchedIndex(),
        );
    }

    /** Pollora builds every hooked class at boot, before WordPress loads in a console command. */
    private function browser(): BrowserConnection
    {
        return lazy(BrowserConnection::class, fn (): array => [
            (string) config('meilifacets.browser.url', ''),
            (string) config('meilifacets.browser.key', ''),
            $this->searchedIndex(),
        ]);
    }

    /** MeiliScout writes to a target index and searches the active one: they differ while a migration is pending. */
    private function searchedIndex(): string
    {
        return IndexNames::active(self::POST_INDEX_KEY);
    }
}
