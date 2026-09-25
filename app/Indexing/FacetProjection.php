<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\TermField;

final readonly class FacetProjection
{
    /**
     * @param  list<array<string, mixed>>  $terms
     * @return array<string, list<string>>
     */
    public static function fromTerms(array $terms): array
    {
        return new TermGrouping(TermField::Slug)->group($terms);
    }
}
