<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Enums\DocumentField;

final readonly class PublishedPosts
{
    private const string PUBLISHED = 'publish';

    /**
     * @return list<string>
     */
    public static function of(string $postType): array
    {
        return [
            FilterExpression::equals(DocumentField::PostType->value, $postType),
            FilterExpression::equals(DocumentField::Status->value, self::PUBLISHED),
        ];
    }
}
