<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Providers\SearchServiceProvider;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use Pollora\MeiliScout\Services\IndexNames;
use ReflectionProperty;
use Tests\TestCase;

trait SearchedIndexSteps
{
    private const string ACTIVE_INDEXES_PRE_OPTION = 'pre_option_meiliscout/active_indexes';

    private const string POST_INDEX_KEY = 'posts';

    /** The name MeiliScout gave the index before it prefixed them. */
    private const string LEGACY_INDEX = 'posts';

    /**
     * @return array<string, string>
     */
    private function legacyIndexes(): array
    {
        return [self::POST_INDEX_KEY => self::LEGACY_INDEX];
    }

    private function engineIndex(): string
    {
        $engine = $this->app->make(SearchEngine::class);

        $this->assertInstanceOf(MeilisearchEngine::class, $engine);

        return new ReflectionProperty(MeilisearchEngine::class, 'index')->getValue($engine);
    }
}

// Until the first full indexation after an update, MeiliScout still searches the index it built before.
uses(TestCase::class, SearchedIndexSteps::class);

beforeEach(function (): void {
    add_filter(self::ACTIVE_INDEXES_PRE_OPTION, $this->legacyIndexes(...));
    new SearchServiceProvider($this->app)->register();
});

afterEach(function (): void {
    remove_all_filters(self::ACTIVE_INDEXES_PRE_OPTION);
});

it('searches the index meiliscout searches rather than the one it writes to', function (): void {
    expect(IndexNames::target(self::POST_INDEX_KEY))->not->toBe(self::LEGACY_INDEX);
    expect($this->engineIndex())->toBe(self::LEGACY_INDEX);
});

it('hands the browser the index meiliscout searches', function (): void {
    expect($this->app->make(BrowserConnection::class)->index)->toBe(self::LEGACY_INDEX);
});
