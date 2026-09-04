<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Support\PlainText;
use WP_Term;

final readonly class WordPressTermLabels implements TermLabels
{
    /**
     * @param  list<string>  $slugs
     * @return array<string, string>
     */
    public function of(string $taxonomy, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        // One query for the whole facet rather than one per value.
        $terms = get_terms(['taxonomy' => $taxonomy, 'slug' => $slugs, 'hide_empty' => false]);

        return is_array($terms) ? $this->named($terms) : [];
    }

    /**
     * @param  list<mixed>  $terms
     * @return array<string, string>
     */
    private function named(array $terms): array
    {
        $labels = [];

        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $labels[$term->slug] = PlainText::from($term->name);
            }
        }

        return $labels;
    }
}
