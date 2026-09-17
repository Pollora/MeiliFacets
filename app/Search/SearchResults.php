<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Enums\DocumentField;

final readonly class SearchResults
{
    /**
     * @param  list<array<string, mixed>>  $hits
     * @param  array<string, array<string, int>>  $distributions  taxonomy to slug to count
     * @param  array<string, array<string, float>>  $facetStats  numeric field to its min and max
     * @param  array<string, array<string, int>>  $unfilteredDistributions  taxonomy to slug to count, before any visitor filter; empty when there was none
     * @param  array<string, int>  $sortMatches  filtering sort to the hits it would keep
     */
    public function __construct(
        public array $hits,
        public int $total,
        public array $distributions,
        public array $facetStats = [],
        public array $unfilteredDistributions = [],
        public array $sortMatches = [],
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(): array
    {
        $cards = array_map(
            static fn (array $hit): mixed => $hit[DocumentField::Card->value] ?? null,
            $this->hits
        );

        return array_values(array_filter($cards, is_array(...)));
    }

    /**
     * @return array<string, int>
     */
    public function distribution(string $taxonomy): array
    {
        return $this->distributions[$taxonomy] ?? [];
    }

    /**
     * @return array<string, int>|null null when nothing narrowed the listing
     */
    public function unfilteredDistribution(string $taxonomy): ?array
    {
        return $this->unfilteredDistributions[$taxonomy] ?? null;
    }
}
