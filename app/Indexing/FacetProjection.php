<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\TermField;

// Meilisearch aggregates each field independently, hence one field per taxonomy.
final class FacetProjection
{
    /**
     * @param  list<array<string, mixed>>  $terms
     * @return array<string, list<string>>
     */
    public static function fromTerms(array $terms): array
    {
        $facets = [];

        foreach ($terms as $term) {
            $taxonomy = self::readString($term, TermField::Taxonomy);
            $slug = self::readString($term, TermField::Slug);

            if ($taxonomy === null || $slug === null) {
                continue;
            }

            $facets[$taxonomy][] = $slug;
        }

        return array_map(
            static fn (array $slugs): array => array_values(array_unique($slugs)),
            $facets
        );
    }

    /**
     * @param  array<string, mixed>  $term
     */
    private static function readString(array $term, TermField $field): ?string
    {
        $value = $term[$field->value] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
