<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** Meilisearch's default order; MeiliScout 2 replaces it with `sort` first. */
enum RankingRule: string
{
    case Words = 'words';
    case Typo = 'typo';
    case Proximity = 'proximity';
    case AttributeRank = 'attributeRank';
    case Sort = 'sort';
    case WordPosition = 'wordPosition';
    case Exactness = 'exactness';

    /**
     * @return list<string>
     */
    public static function relevanceFirst(): array
    {
        return array_column(self::cases(), 'value');
    }
}
