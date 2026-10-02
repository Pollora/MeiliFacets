<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
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
        private CardProjector $cards,
        private ProductPriceProjector $prices,
        private ShopTaxLocation $shopTaxLocation,
        private AnonymousVisitor $anonymousVisitor,
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
            ...$this->asShopVisitor(fn (): array => $this->shopFields($post)),
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

    /**
     * @return array<string, mixed>
     */
    private function shopFields(WP_Post $post): array
    {
        $card = [DocumentField::Card->value => $this->withListedVariants($this->cards->project($post))];
        $price = $this->prices->project($post);

        return $price === [] ? $card : [...$card, DocumentField::Price->value => $price];
    }

    /**
     * A list with gaps, left by `array_filter()`, would be stored as an object, which the listing ignores.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function withListedVariants(array $card): array
    {
        $variants = $card[CardField::Variants->value] ?? null;

        return is_array($variants) ? array_replace($card, [CardField::Variants->value => array_values($variants)]) : $card;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function asShopVisitor(Closure $read): mixed
    {
        return $this->shopTaxLocation->during(fn (): mixed => $this->anonymousVisitor->during($read));
    }
}
