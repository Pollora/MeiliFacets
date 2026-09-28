<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use WP_Post_Type;

final readonly class SearchablePostTypes
{
    public function __construct(private IndexedPostTypes $indexed) {}

    /**
     * @return list<string> in the order MeiliScout indexes them
     */
    public function all(): array
    {
        return array_values(array_filter($this->indexed->all(), $this->isSearchable(...)));
    }

    public function contains(string $postType): bool
    {
        return in_array($postType, $this->all(), true);
    }

    private function isSearchable(string $postType): bool
    {
        $object = get_post_type_object($postType);

        return $object instanceof WP_Post_Type && $object->public && ! $object->exclude_from_search;
    }
}
