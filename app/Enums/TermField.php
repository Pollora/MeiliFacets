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
}
