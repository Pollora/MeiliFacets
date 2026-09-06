<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Enums\TermField;
use WP_Term;

final readonly class WordPressTermHierarchy implements TermHierarchy
{
    private const string RESOURCE_TYPE = 'taxonomy';

    /**
     * @return list<array<string, mixed>>
     */
    public function ancestorsOf(int $termId, string $taxonomy): array
    {
        $ancestors = [];

        foreach (get_ancestors($termId, $taxonomy, self::RESOURCE_TYPE) as $ancestorId) {
            $term = get_term((int) $ancestorId, $taxonomy);

            if ($term instanceof WP_Term) {
                $ancestors[] = $this->describe($term);
            }
        }

        return $ancestors;
    }

    /**
     * @return list<string>
     */
    public function childrenOf(int $termId, string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'parent' => $termId,
            'hide_empty' => false,
        ]);

        return is_array($terms)
            ? array_values(array_map(static fn (WP_Term $term): string => $term->slug, array_filter($terms, static fn (mixed $term): bool => $term instanceof WP_Term)))
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(WP_Term $term): array
    {
        // `terms.name` is searchable: an ancestor without it would be filterable but not findable.
        return [
            TermField::TermId->value => (int) $term->term_id,
            TermField::Name->value => $term->name,
            TermField::Slug->value => $term->slug,
            TermField::Taxonomy->value => $term->taxonomy,
            TermField::TermTaxonomyId->value => (int) $term->term_taxonomy_id,
            TermField::Parent->value => (int) $term->parent,
        ];
    }
}
