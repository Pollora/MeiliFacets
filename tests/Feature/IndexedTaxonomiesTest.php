<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Tests\TestCase;

trait IndexedTaxonomiesHelpers
{
    private const string INDEXED_POST_TYPES_OPTION = 'pre_option_meiliscout/indexed_post_types';

    /**
     * @param  list<string>  $postTypes
     */
    private function indexOnly(array $postTypes): void
    {
        remove_all_filters(self::INDEXED_POST_TYPES_OPTION);
        add_filter(self::INDEXED_POST_TYPES_OPTION, static fn (): array => $postTypes);
    }
}

/** One instance lives as long as a worker or a batch: the MeiliScout setting may change under it. */
uses(TestCase::class, IndexedTaxonomiesHelpers::class);

afterEach(function (): void {
    remove_all_filters(self::INDEXED_POST_TYPES_OPTION);
});

it('follows the post types indexed since its last answer', function (): void {
    $taxonomies = new IndexedTaxonomies(new IndexedPostTypes);

    $this->indexOnly(['page']);
    expect($taxonomies->all())->not->toContain('category');

    $this->indexOnly(['post']);
    expect($taxonomies->all())->toContain('category');
});
