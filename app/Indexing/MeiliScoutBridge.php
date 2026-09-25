<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Search\EngineLimits;
use Pollora\Attributes\Filter;
use Pollora\MeiliScout\Contracts\Indexable;
use Pollora\MeiliScout\Indexables\PostIndexable;
use WP_Post;

final class MeiliScoutBridge
{
    private ?FacetedPostIndexable $facetedPosts = null;

    public function __construct(
        private readonly PostDocument $postDocument,
        private readonly IndexAttributes $attributes,
        private readonly SearchableAttributes $searchable,
        private readonly IndexedTaxonomies $taxonomies,
        private readonly EngineLimits $limits,
    ) {}

    /**
     * Runs inside `formatForIndexing()`, so it applies on every indexing path.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    #[Filter('meiliscout/post/document')]
    public function addModuleFields(array $document, WP_Post $post): array
    {
        return $this->postDocument->complete($document, $post);
    }

    /**
     * The filter hands over a list, not a keyed map: the swap matches on type.
     *
     * @param  list<Indexable>  $indexables
     * @return list<Indexable>
     */
    #[Filter('meiliscout/indexables')]
    public function declareFacetAttributes(array $indexables): array
    {
        return array_map(
            fn (Indexable $indexable): Indexable => $this->needsFacetAttributes($indexable)
                ? $this->facetedPosts()
                : $indexable,
            $indexables
        );
    }

    // `meiliscout/indexables` runs on every indexed item, not once per request.
    private function facetedPosts(): FacetedPostIndexable
    {
        return $this->facetedPosts ??= new FacetedPostIndexable(
            $this->attributes,
            $this->searchable,
            $this->taxonomies,
            $this->limits
        );
    }

    private function needsFacetAttributes(Indexable $indexable): bool
    {
        return $indexable instanceof PostIndexable
            && ! $indexable instanceof FacetedPostIndexable;
    }
}
