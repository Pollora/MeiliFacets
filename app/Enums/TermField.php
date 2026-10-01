<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum TermField: string
{
    case TermId = 'term_id';
    case Name = 'name';
    case Slug = 'slug';
    case Taxonomy = 'taxonomy';
    case TermTaxonomyId = 'term_taxonomy_id';
    case Parent = 'parent';

    /**
     * @param  array<string, mixed>  $term
     */
    public function textIn(array $term): ?string
    {
        $value = $term[$this->value] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
