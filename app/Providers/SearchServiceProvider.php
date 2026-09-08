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
use Pollora\MeiliScout\Indexables\PostIndexable;
use Pollora\MeiliScout\Services\ClientFactory;

/** How a search is sent, and to which of the engine's two addresses. */
final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FacetCounter::class, DisjunctiveFacetCounter::class);
        $this->app->scoped(SearchEngine::class, $this->engine(...));
        $this->app->scoped(BrowserConnection::class, $this->browser(...));
        $this->app->scoped(EngineLimits::class, fn (): EngineLimits => new EngineLimits(
            (int) config('meilifacets.engine.reachable_hits', EngineLimits::DEFAULT_REACHABLE_HITS)
        ));
    }

    /** The only place MeiliScout is reached from: one border with the plugin. */
    private function engine(): SearchEngine
    {
        return new MeilisearchEngine(
            ClientFactory::getSearchClient(),
            new PostIndexable()->getIndexName(),
        );
    }

    private function browser(): BrowserConnection
    {
        return new BrowserConnection(
            (string) config('meilifacets.browser.url', ''),
            (string) config('meilifacets.browser.key', ''),
            new PostIndexable()->getIndexName(),
        );
    }
}
