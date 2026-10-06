<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\VariantField;

/**
 * One way a product is sold — a size, a colour — inside the product's card.
 *
 * @phpstan-type StoredVariant array{
 *     facets: array<string, list<string>>,
 *     price: float,
 *     fields: array<string, mixed>,
 *     in_stock: bool,
 * }
 *
 * @phpstan-import-type Selection from ListingState
 */
final readonly class CardVariant
{
    /**
     * @param  array<string, list<string>>  $facets  taxonomy to the term slugs this variant carries
     * @param  array<string, mixed>  $fields  card fields shown instead of the product's when this variant is chosen
     */
    public function __construct(
        public array $facets,
        public float $price,
        public array $fields = [],
        public bool $inStock = true,
    ) {}

    /** Anything without a finite price is not a variant. */
    public static function read(mixed $stored): ?self
    {
        if (! is_array($stored) || ! self::isPrice($stored[VariantField::Price->value] ?? null)) {
            return null;
        }

        return new self(
            self::facetsOf($stored[VariantField::Facets->value] ?? null),
            (float) $stored[VariantField::Price->value],
            self::fieldsOf($stored[VariantField::Fields->value] ?? null),
            ($stored[VariantField::InStock->value] ?? true) !== false,
        );
    }

    /**
     * @return StoredVariant
     */
    public function toArray(): array
    {
        return [
            VariantField::Facets->value => $this->facets,
            VariantField::Price->value => $this->price,
            VariantField::Fields->value => $this->fields,
            VariantField::InStock->value => $this->inStock,
        ];
    }

    /**
     * A facet the variant does not carry does not rule it out.
     *
     * @param  Selection  $selected
     */
    public function matches(array $selected, Range $price): bool
    {
        foreach ($selected as $taxonomy => $slugs) {
            if (isset($this->facets[$taxonomy]) && array_intersect($this->facets[$taxonomy], $slugs) === []) {
                return false;
            }
        }

        return $price->contains($this->price);
    }

    private static function isPrice(mixed $stored): bool
    {
        return is_int($stored) || (is_float($stored) && is_finite($stored));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function facetsOf(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }

        $facets = [];

        foreach ($stored as $taxonomy => $slugs) {
            if (is_array($slugs)) {
                $facets[$taxonomy] = array_values(array_filter($slugs, is_string(...)));
            }
        }

        return $facets;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fieldsOf(mixed $stored): array
    {
        return is_array($stored) ? $stored : [];
    }
}
