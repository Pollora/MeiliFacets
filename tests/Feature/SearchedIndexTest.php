<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Providers\SearchServiceProvider;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use PHPUnit\Framework\Attributes\Test;
use Pollora\MeiliScout\Services\IndexNames;
use ReflectionProperty;
use Tests\TestCase;

/** Until the first full indexation after an update, MeiliScout still searches the index it built before. */
final class SearchedIndexTest extends TestCase
{
    private const string ACTIVE_INDEXES_OPTION = 'pre_option_meiliscout/active_indexes';

    private const string POSTS = 'posts';

    /** The name MeiliScout gave the index before it prefixed them. */
    private const string INDEX_BUILT_BEFORE = 'posts';

    protected function setUp(): void
    {
        parent::setUp();

        add_filter(self::ACTIVE_INDEXES_OPTION, $this->indexesBuiltBefore(...));
        new SearchServiceProvider($this->app)->register();
    }

    protected function tearDown(): void
    {
        remove_all_filters(self::ACTIVE_INDEXES_OPTION);

        parent::tearDown();
    }

    #[Test]
    public function it_searches_the_index_meiliscout_searches_rather_than_the_one_it_writes_to(): void
    {
        $this->assertNotSame(self::INDEX_BUILT_BEFORE, IndexNames::target(self::POSTS));
        $this->assertSame(self::INDEX_BUILT_BEFORE, $this->engineIndex());
    }

    #[Test]
    public function it_hands_the_browser_the_index_meiliscout_searches(): void
    {
        $this->assertSame(self::INDEX_BUILT_BEFORE, $this->app->make(BrowserConnection::class)->index);
    }

    /**
     * @return array<string, string>
     */
    private function indexesBuiltBefore(): array
    {
        return [self::POSTS => self::INDEX_BUILT_BEFORE];
    }

    private function engineIndex(): string
    {
        $engine = $this->app->make(SearchEngine::class);

        $this->assertInstanceOf(MeilisearchEngine::class, $engine);

        return new ReflectionProperty(MeilisearchEngine::class, 'index')->getValue($engine);
    }
}
