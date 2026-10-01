<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** One instance lives as long as a worker or a batch: the MeiliScout setting may change under it. */
final class IndexedTaxonomiesTest extends TestCase
{
    private const string INDEXED_POST_TYPES_OPTION = 'pre_option_meiliscout/indexed_post_types';

    protected function tearDown(): void
    {
        remove_all_filters(self::INDEXED_POST_TYPES_OPTION);

        parent::tearDown();
    }

    #[Test]
    public function it_follows_the_post_types_indexed_since_its_last_answer(): void
    {
        $taxonomies = new IndexedTaxonomies(new IndexedPostTypes);

        $this->indexOnly(['page']);
        $this->assertNotContains('category', $taxonomies->all());

        $this->indexOnly(['post']);
        $this->assertContains('category', $taxonomies->all());
    }

    /**
     * @param  list<string>  $postTypes
     */
    private function indexOnly(array $postTypes): void
    {
        remove_all_filters(self::INDEXED_POST_TYPES_OPTION);
        add_filter(self::INDEXED_POST_TYPES_OPTION, static fn (): array => $postTypes);
    }
}
