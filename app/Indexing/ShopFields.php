<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\DocumentKind;
use WP_Post;

/** The card, the price, the stock and the kind of a product's document, read as an anonymous visitor of the shop. */
final readonly class ShopFields
{
    private const array FIELDS_SET_BY_THE_MODULE = [CardField::Variants->value, CardField::OutOfStock->value];

    public function __construct(
        private CardProjector $cards,
        private ProductVariants $variants,
        private ProductStock $stock,
        private ProductPriceProjector $prices,
        private ShopTaxLocation $shopTaxLocation,
        private AnonymousVisitor $anonymousVisitor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array
    {
        return $this->asShopVisitor(fn (): array => $this->fields($post));
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(WP_Post $post): array
    {
        $card = $this->card($post);
        $price = $this->prices->project($post);

        if ($price === []) {
            return [DocumentField::Card->value => $card];
        }

        return [
            DocumentField::Card->value => $card,
            DocumentField::Price->value => $price,
            ...$this->inStockField($card),
            ...$this->kindField($card),
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, int>
     */
    private function inStockField(array $card): array
    {
        $isOutOfStock = isset($card[CardField::OutOfStock->value]);

        return [DocumentField::InStock->value => $isOutOfStock ? 0 : 1];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, string>
     */
    private function kindField(array $card): array
    {
        $hasVariantDocuments = isset($card[CardField::Variants->value]);

        return $hasVariantDocuments ? [DocumentField::Kind->value => DocumentKind::Parent->value] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(WP_Post $post): array
    {
        $card = array_diff_key($this->cards->project($post), array_flip(self::FIELDS_SET_BY_THE_MODULE));
        $variants = $this->variants->project($post);

        return [
            ...$card,
            ...$this->stock->project($post),
            ...($variants === [] ? [] : [CardField::Variants->value => $variants]),
        ];
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
