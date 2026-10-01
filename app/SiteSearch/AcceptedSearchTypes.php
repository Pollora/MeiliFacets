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
     * @throws UnsearchableFields
     */
    public function all(): array
    {
        $accepted = array_intersect_key($this->declared->all(), array_flip($this->indexed->all()));
        array_walk($accepted, $this->ensureSearchable(...));

        return $accepted;
    }

    private function ensureSearchable(SearchableType $type): void
    {
        $outside = $this->searchOn->outside($type->searchOn);

        if ($outside !== []) {
            throw UnsearchableFields::for($type->postType, $outside);
        }
    }
}
