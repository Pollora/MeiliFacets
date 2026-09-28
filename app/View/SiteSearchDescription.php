<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\SiteSearch\SearchableType;
use Modules\MeiliFacets\SiteSearch\SearchRoot;

/** The shape is the PHP/JavaScript contract of a search root, as `ListingDescription` is for a listing. */
final readonly class SiteSearchDescription
{
    public function __construct(
        private CountLabel $countLabel,
        private Preconnect $preconnect,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     minChars: int,
     *     delay: int,
     *     limit: int,
     *     types: list<array<string, mixed>>,
     *     countPattern: string,
     *     sectionPattern: string,
     *     locale: string,
     *     preconnect: string,
     * }
     */
    public function of(SearchRoot $root): array
    {
        return [
            'name' => $root->name,
            'minChars' => $root->settings->minChars,
            'delay' => $root->settings->delay,
            'limit' => $root->settings->limit,
            'types' => array_values(array_map($this->type(...), $root->types)),
            'countPattern' => __(':count result|:count results'),
            'sectionPattern' => __(':heading: :count'),
            'locale' => $this->countLabel->languageTag(),
            'preconnect' => $this->preconnect->origin(),
        ];
    }

    /**
     * @return array{
     *     postType: string,
     *     heading: string,
     *     seeAllLabel: string,
     *     baseFilter: list<string>,
     *     searchOn: list<string>,
     *     archive: ?string,
     * }
     */
    private function type(SearchableType $type): array
    {
        return [
            'postType' => $type->postType,
            'heading' => $type->heading,
            'seeAllLabel' => $type->seeAllLabel,
            'baseFilter' => $type->baseFilter,
            'searchOn' => $type->searchOn,
            'archive' => $type->archive,
        ];
    }
}
