<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Pollora\Attributes\Filter;
use Pollora\MeiliScout\Contracts\Indexable;
use Pollora\MeiliScout\Indexables\PostIndexable;
use WP_Post;

final readonly class MeiliScoutBridge
{
    public function __construct(
        private PostDocument $postDocument,
        private FacetedPostIndexable $facetedPosts,
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
                ? $this->facetedPosts
                : $indexable,
            $indexables
        );
    }

    private function needsFacetAttributes(Indexable $indexable): bool
    {
        return $indexable instanceof PostIndexable
            && ! $indexable instanceof FacetedPostIndexable;
    }
}
