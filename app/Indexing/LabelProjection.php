<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\TermField;
use Modules\MeiliFacets\Support\PlainText;

/** What a visitor reads on the site, not the slug: the field a search ranks a brand or a category by. */
final readonly class LabelProjection
{
    /**
     * @param  list<array<string, mixed>>  $terms
     * @return array<string, list<string>>
     */
    public static function fromTerms(array $terms): array
    {
        return array_map(
            self::decoded(...),
            new TermGrouping(TermField::Name)->group($terms)
        );
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function decoded(array $names): array
    {
        return array_map(PlainText::from(...), $names);
    }
}
