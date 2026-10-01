<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

/**
 * The labelled taxonomies follow the post types MeiliScout indexes, an option of the local database.
 */
trait PinsIndexedPostTypes
{
    private const string INDEXED_POST_TYPES_OPTION = 'pre_option_meiliscout/indexed_post_types';

    private const array PINNED_POST_TYPES = ['post', 'product'];

    protected function pinIndexedPostTypes(): void
    {
        add_filter(self::INDEXED_POST_TYPES_OPTION, $this->pinnedPostTypes(...));
    }

    protected function unpinIndexedPostTypes(): void
    {
        remove_all_filters(self::INDEXED_POST_TYPES_OPTION);
    }

    /**
     * @return list<string>
     */
    private function pinnedPostTypes(): array
    {
        return self::PINNED_POST_TYPES;
    }
}
