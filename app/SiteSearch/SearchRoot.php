<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

final readonly class SearchRoot
{
    public const string DEFAULT_NAME = 'search';

    /**
     * @param  array<string, SearchableType>  $types  keyed by post type, in the order they were declared
     */
    public function __construct(
        public string $name,
        public SearchSettings $settings,
        public array $types,
    ) {}

    /**
     * @throws SearchTypeRefused
     */
    public function type(string $postType): SearchableType
    {
        return $this->types[$postType] ?? throw SearchTypeRefused::for($postType, array_keys($this->types));
    }
}
