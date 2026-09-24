<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class Sort
{
    /**
     * @param  list<string>  $expressions  Meilisearch sort expressions
     */
    public function __construct(
        public string $label,
        public array $expressions,
        public ?SortFilter $filter = null,
    ) {}

    public static function filtering(string $label, SortFilter $filter): self
    {
        return new self($label, [], $filter);
    }

    public function isFiltering(): bool
    {
        return $this->filter instanceof SortFilter;
    }

    public function filtersOn(string $field): bool
    {
        return $this->filter instanceof SortFilter && $this->filter->field === $field;
    }
}
