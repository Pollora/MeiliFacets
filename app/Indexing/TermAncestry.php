<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Enums\TermField;

// A filter on a parent term has to match the documents filed under its children.
final class TermAncestry
{
    private const int NO_TERM = 0;

    private const string NO_TAXONOMY = '';

    /** @var array<string, list<array<string, mixed>>> */
    private array $chains = [];

    public function __construct(private readonly TermHierarchy $hierarchy) {}

    /**
     * @param  list<array<string, mixed>>  $terms
     * @return list<array<string, mixed>>
     */
    public function expand(array $terms): array
    {
        $expanded = array_merge($terms, ...array_map($this->chainOf(...), $terms));

        return $this->withoutRepeats($expanded);
    }

    /**
     * A document filed under both a parent and its child would carry the parent twice.
     *
     * @param  list<array<string, mixed>>  $terms
     * @return list<array<string, mixed>>
     */
    private function withoutRepeats(array $terms): array
    {
        $unique = [];

        foreach ($terms as $term) {
            $unique[$this->keyOf($term)] = $term;
        }

        return array_values($unique);
    }

    /**
     * @param  array<string, mixed>  $term
     * @return list<array<string, mixed>>
     */
    private function chainOf(array $term): array
    {
        if (! $this->hasAncestors($term)) {
            return [];
        }

        return $this->chains[$this->keyOf($term)] ??= $this->hierarchy->ancestorsOf(
            $this->readId($term, TermField::TermId),
            $this->taxonomyOf($term)
        );
    }

    /**
     * @param  array<string, mixed>  $term
     */
    private function hasAncestors(array $term): bool
    {
        // A flat taxonomy always reports no parent.
        return $this->readId($term, TermField::Parent) !== self::NO_TERM
            && $this->taxonomyOf($term) !== self::NO_TAXONOMY;
    }

    /**
     * @param  array<string, mixed>  $term
     */
    private function keyOf(array $term): string
    {
        return $this->taxonomyOf($term).':'.$this->readId($term, TermField::TermId);
    }

    /**
     * @param  array<string, mixed>  $term
     */
    private function taxonomyOf(array $term): string
    {
        $taxonomy = $term[TermField::Taxonomy->value] ?? null;

        return is_string($taxonomy) ? $taxonomy : self::NO_TAXONOMY;
    }

    /**
     * @param  array<string, mixed>  $term
     */
    private function readId(array $term, TermField $field): int
    {
        $value = $term[$field->value] ?? null;

        return is_numeric($value) ? (int) $value : self::NO_TERM;
    }
}
