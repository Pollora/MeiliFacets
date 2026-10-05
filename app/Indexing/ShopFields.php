<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\DocumentField;
use WP_Post;

/** The card and the price a document carries, read as an anonymous visitor of the shop's tax location. */
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
        $card = [DocumentField::Card->value => $this->card($post)];
        $price = $this->prices->project($post);

        return $price === [] ? $card : [...$card, DocumentField::Price->value => $price];
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
