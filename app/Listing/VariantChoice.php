<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\CardField;

/**
 * Shows a card through the variant the active filters point to once a variation attribute is checked, as projected otherwise.
 *
 * @phpstan-import-type Selection from ListingState
 */
final readonly class VariantChoice
{
    private const array FLAGS_OF_THE_CHOSEN_VARIANT = [
        CardField::SeveralVariants->value,
        CardField::OutOfStock->value,
    ];

    private const array FIELDS_SET_BY_THE_MODULE = [
        CardField::Id->value,
        CardField::Variants->value,
        ...self::FLAGS_OF_THE_CHOSEN_VARIANT,
    ];

    /**
     * @param  Selection  $selected
     * @param  list<string>  $variantTaxonomies
     */
    public function __construct(
        private array $selected,
        private Range $price,
        private array $variantTaxonomies,
    ) {}

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function shown(array $card): array
    {
        $variants = $this->read($card[CardField::Variants->value] ?? null);
        unset($card[CardField::Variants->value]);

        $matching = $this->readsVariants() ? $this->matching($variants) : [];

        if ($matching === []) {
            return $card;
        }

        $offered = $this->offered($matching);
        $chosen = $this->cheapest($offered);

        return array_replace(
            $this->unflagged($card),
            $this->overrides($chosen),
            $this->several($offered),
            $this->stock($chosen),
        );
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function unflagged(array $card): array
    {
        return array_diff_key($card, array_flip(self::FLAGS_OF_THE_CHOSEN_VARIANT));
    }

    /**
     * @return list<CardVariant>
     */
    private function read(mixed $stored): array
    {
        $variants = array_map(CardVariant::read(...), $this->listed($stored));

        return array_values(array_filter($variants));
    }

    /**
     * `json_decode` keeps an object's keys in written order; the browser lists integer keys ascending.
     *
     * @return list<mixed>
     */
    private function listed(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }

        ksort($stored);

        return array_is_list($stored) ? $stored : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function overrides(CardVariant $variant): array
    {
        return array_diff_key($variant->fields, array_flip(self::FIELDS_SET_BY_THE_MODULE));
    }

    private function readsVariants(): bool
    {
        return array_intersect(array_keys($this->selected), $this->variantTaxonomies) !== [];
    }

    /**
     * @param  list<CardVariant>  $variants
     * @return list<CardVariant>
     */
    private function matching(array $variants): array
    {
        return array_values(array_filter(
            $variants,
            fn (CardVariant $variant): bool => $variant->matches($this->selected, $this->price),
        ));
    }

    /**
     * @param  non-empty-list<CardVariant>  $matching
     * @return non-empty-list<CardVariant>
     */
    private function offered(array $matching): array
    {
        $inStock = array_values(array_filter($matching, static fn (CardVariant $variant): bool => $variant->inStock));

        return $inStock === [] ? $matching : $inStock;
    }

    /**
     * The first listed wins a tie.
     *
     * @param  non-empty-list<CardVariant>  $variants
     */
    private function cheapest(array $variants): CardVariant
    {
        return array_reduce(
            $variants,
            static fn (CardVariant $cheapest, CardVariant $variant): CardVariant => $variant->price < $cheapest->price ? $variant : $cheapest,
            $variants[0],
        );
    }

    /**
     * @param  list<CardVariant>  $offered
     * @return array<string, true>
     */
    private function several(array $offered): array
    {
        return count($offered) > 1 ? [CardField::SeveralVariants->value => true] : [];
    }

    /**
     * @return array<string, true>
     */
    private function stock(CardVariant $chosen): array
    {
        return $chosen->inStock ? [] : [CardField::OutOfStock->value => true];
    }
}
