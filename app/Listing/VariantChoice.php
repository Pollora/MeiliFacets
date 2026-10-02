<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\CardField;

/**
 * Shows a card through the variant the active filters point to, and as projected when none concerns its variants.
 *
 * @phpstan-import-type Selection from ListingState
 */
final readonly class VariantChoice
{
    private const array FIELDS_SET_BY_THE_MODULE = [
        CardField::Id->value,
        CardField::Variants->value,
        CardField::SeveralVariants->value,
    ];

    /**
     * @param  Selection  $selected
     */
    public function __construct(
        private array $selected = [],
        private Range $price = new Range,
    ) {}

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function shown(array $card): array
    {
        $variants = $this->read($card[CardField::Variants->value] ?? null);
        unset($card[CardField::Variants->value]);

        $matching = $this->isConcerned($variants) ? $this->matching($variants) : [];

        if ($matching === []) {
            return $card;
        }

        return array_replace($card, $this->overrides($this->cheapest($matching)), $this->several($matching));
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

    /**
     * @param  list<CardVariant>  $variants
     */
    private function isConcerned(array $variants): bool
    {
        return ! $this->price->isEmpty()
            || array_any($variants, fn (CardVariant $variant): bool => $variant->carriesAny($this->selected));
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
     * @param  list<CardVariant>  $matching
     * @return array<string, true>
     */
    private function several(array $matching): array
    {
        return count($matching) > 1 ? [CardField::SeveralVariants->value => true] : [];
    }
}
