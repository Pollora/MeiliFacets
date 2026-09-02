<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum TermField: string
{
    case Slug = 'slug';
    case Taxonomy = 'taxonomy';
}
