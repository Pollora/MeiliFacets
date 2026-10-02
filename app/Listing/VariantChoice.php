<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\CardField;

/** Shows a card through the variant the active filters point to, and as projected when none concerns its variants. */
final readonly class VariantChoice
{
    /**
     * @param  array<string, list<string>>  $selected  taxonomy to selected slugs
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

        return array_replace($card, $this->cheapest($matching)->fields, $this->several($matching));
    }

    /**
     * @return list<CardVariant>
     */
    private function read(mixed $stored): array
    {
        $variants = is_array($stored) ? array_map(CardVariant::read(...), array_values($stored)) : [];

        return array_values(array_filter($variants));
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
