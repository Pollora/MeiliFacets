<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

final readonly class SearchableType
{
    /**
     * @param  list<string>  $baseFilter  clauses every query on this type carries
     * @param  list<string>  $searchOn  `attributesToSearchOn`: a subset of the index's searchable attributes
     *
     * @throws NoFieldToSearch
     */
    public function __construct(
        public string $postType,
        public string $heading,
        public string $seeAllLabel,
        public array $baseFilter,
        public array $searchOn,
        public string $card,
        public ?string $archive,
    ) {
        if ($searchOn === []) {
            throw NoFieldToSearch::for($postType);
        }
    }

    public function withHeading(string $heading): self
    {
        return $this->with(['heading' => $heading]);
    }

    public function withSeeAllLabel(string $seeAllLabel): self
    {
        return $this->with(['seeAllLabel' => $seeAllLabel]);
    }

    public function withCard(string $card): self
    {
        return $this->with(['card' => $card]);
    }

    /**
     * @param  list<string>  $searchOn
     */
    public function withSearchOn(array $searchOn): self
    {
        return $this->with(['searchOn' => $searchOn]);
    }

    /**
     * @param  array<string, mixed>  $changes  constructor arguments, by name
     */
    private function with(array $changes): self
    {
        $arguments = [...get_object_vars($this), ...$changes];

        return new self(...$arguments);
    }
}
