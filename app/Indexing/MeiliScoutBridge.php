<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Enums\DocumentField;
use Pollora\Attributes\Filter;
use Pollora\MeiliScout\Contracts\Indexable;
use Pollora\MeiliScout\Indexables\PostIndexable;
use WP_Post;

final class MeiliScoutBridge
{
    private ?FacetedPostIndexable $facetedPosts = null;

    public function __construct(
        private readonly TermAncestry $ancestry,
        private readonly CardProjector $cards,
        private readonly IndexAttributes $attributes,
    ) {}

    /**
     * Runs inside `formatForIndexing()`, so it applies on every indexing path.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    #[Filter('meiliscout/post/document')]
    public function addFacets(array $document): array
    {
        $terms = $document[DocumentField::Terms->value] ?? [];

        if (! is_array($terms)) {
            return $document;
        }

        $document[DocumentField::Facets->value] = FacetProjection::fromTerms(
            $this->ancestry->expand($terms)
        );

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    #[Filter('meiliscout/post/document')]
    public function addCard(array $document, WP_Post $post): array
    {
        $document[DocumentField::Card->value] = $this->cards->project($post);

        return $document;
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
        return $this->facetedPosts ??= new FacetedPostIndexable($this->attributes);
    }

    private function needsFacetAttributes(Indexable $indexable): bool
    {
        return $indexable instanceof PostIndexable
            && ! $indexable instanceof FacetedPostIndexable;
    }
}
