<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Meilisearch\Contracts\SearchQuery;

final readonly class MeilisearchQuery
{
    private const array OPTIONS = [
        'filter', 'facets', 'sort', 'distinct', 'attributesToRetrieve', 'attributesToSearchOn',
    ];

    public function __construct(private string $index) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function from(array $query): SearchQuery
    {
        $search = (new SearchQuery)
            ->setIndexUid($this->index)
            ->setQuery($this->text($query['q'] ?? ''))
            ->setHitsPerPage(max(0, $this->number($query['hitsPerPage'] ?? 0)))
            ->setPage($this->number($query['page'] ?? 1));

        foreach (self::OPTIONS as $option) {
            $search = $this->apply($search, $option, $query[$option] ?? null);
        }

        return $search;
    }

    private function apply(SearchQuery $search, string $option, mixed $value): SearchQuery
    {
        return is_string($value)
            ? $this->applyText($search, $option, $value)
            : $this->applyNames($search, $option, $this->namesIn($value));
    }

    private function applyText(SearchQuery $search, string $option, string $value): SearchQuery
    {
        return match (true) {
            $value === '' => $search,
            $option === 'filter' => $search->setFilter([$value]),
            $option === 'distinct' => $search->setDistinct($value),
            default => $search,
        };
    }

    /**
     * @param  list<non-empty-string>  $names
     */
    private function applyNames(SearchQuery $search, string $option, array $names): SearchQuery
    {
        return match (true) {
            $names === [] => $search,
            $option === 'facets' => $search->setFacets($names),
            $option === 'sort' => $search->setSort($names),
            $option === 'attributesToSearchOn' => $search->setAttributesToSearchOn($names),
            $option === 'attributesToRetrieve' => $search->setAttributesToRetrieve($names),
            default => $search,
        };
    }

    /**
     * @return list<non-empty-string>
     */
    private function namesIn(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $name): bool => is_string($name) && $name !== '',
        ));
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function number(mixed $value): int
    {
        return is_scalar($value) ? (int) $value : 0;
    }
}
