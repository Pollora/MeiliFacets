<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\TermField;

// Meilisearch aggregates and ranks each field independently, hence one field per taxonomy.
final readonly class TermGrouping
{
    public function __construct(private TermField $kept) {}

    /**
     * @param  list<array<string, mixed>>  $terms
     * @return array<string, list<string>>
     */
    public function group(array $terms): array
    {
        $grouped = [];

        foreach ($terms as $term) {
            $taxonomy = $this->readString($term, TermField::Taxonomy);
            $value = $this->readString($term, $this->kept);

            if ($taxonomy === null || $value === null) {
                continue;
            }

            $grouped[$taxonomy][] = $value;
        }

        return $this->deduplicate($grouped);
    }

    /**
     * @param  array<string, list<string>>  $grouped
     * @return array<string, list<string>>
     */
    private function deduplicate(array $grouped): array
    {
        return array_map(
            static fn (array $values): array => array_values(array_unique($values)),
            $grouped
        );
    }

    /**
     * @param  array<string, mixed>  $term
     */
    private function readString(array $term, TermField $field): ?string
    {
        $value = $term[$field->value] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
