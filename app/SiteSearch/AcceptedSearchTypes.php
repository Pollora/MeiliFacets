<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Indexing\IndexedPostTypes;

final readonly class AcceptedSearchTypes
{
    public function __construct(
        private SearchableTypes $declared,
        private IndexedPostTypes $indexed,
        private AttributesToSearchOn $searchOn,
    ) {}

    /**
     * @return array<string, SearchableType> keyed by post type, in the order they were declared
     *
     * @throws FieldsOutsideSearchOrder
     */
    public function all(): array
    {
        $accepted = array_intersect_key($this->declared->all(), array_flip($this->indexed->all()));
        array_walk($accepted, $this->ensureWithinOrder(...));

        return $accepted;
    }

    /**
     * @throws SearchTypeRefused
     * @throws FieldsOutsideSearchOrder
     */
    public function get(string $postType): SearchableType
    {
        $accepted = $this->all();

        if (! array_key_exists($postType, $accepted)) {
            throw SearchTypeRefused::for($postType, array_keys($accepted));
        }

        return $accepted[$postType];
    }

    private function ensureWithinOrder(SearchableType $type): void
    {
        $outside = $this->searchOn->outside($type->searchOn);

        if ($outside !== []) {
            throw FieldsOutsideSearchOrder::for($type->postType, $outside);
        }
    }
}
