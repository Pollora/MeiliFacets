<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Enums\DocumentField;

final readonly class SearchResults
{
    /**
     * @param  list<array<string, mixed>>  $hits
     * @param  array<string, array<string, int>>  $distributions  taxonomy to slug to count
     */
    public function __construct(
        public array $hits,
        public int $total,
        public array $distributions,
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
}
