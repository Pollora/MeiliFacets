<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\TermField;
use WP_Post;

/** The fields the module adds to the document MeiliScout builds for a post. */
final readonly class PostDocument
{
    public function __construct(
        private TermAncestry $ancestry,
        private IndexedTaxonomies $taxonomies,
        private PostText $postText,
        private ShopFields $shopFields,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function complete(array $document, WP_Post $post): array
    {
        return [
            ...$document,
            ...$this->termFields($document),
            ...$this->textFields($post),
            ...$this->shopFields->project($post),
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function termFields(array $document): array
    {
        $terms = $document[DocumentField::Terms->value] ?? [];

        if (! is_array($terms)) {
            return [];
        }

        $expanded = $this->ancestry->expand($terms);

        return [
            DocumentField::Facets->value => FacetProjection::fromTerms($expanded),
            DocumentField::Labels->value => LabelProjection::fromTerms($this->labelledOnly($expanded)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     * @return list<array<string, mixed>>
     */
    private function labelledOnly(array $terms): array
    {
        $labelled = $this->taxonomies->labelled();

        return array_values(array_filter(
            $terms,
            static fn (array $term): bool => in_array(TermField::Taxonomy->textIn($term), $labelled, true)
        ));
    }

    /**
     * @return array<string, string>
     */
    private function textFields(WP_Post $post): array
    {
        return [
            DocumentField::Excerpt->value => $this->postText->excerpt($post),
            DocumentField::Content->value => $this->postText->content($post),
        ];
    }
}
