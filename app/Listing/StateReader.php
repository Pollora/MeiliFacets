<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Support\UrlParameters;

final readonly class StateReader
{
    public const string VALUE_SEPARATOR = ',';

    /** A query string is public input: both bounds keep a crafted URL cheap. */
    public const int MAX_QUERY_LENGTH = 200;

    public function __construct(private UrlParameters $parameters) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function read(Listing $listing, array $query): ListingState
    {
        return new ListingState(
            $this->facets($listing, $query),
            $this->sort($listing, $query),
            $this->page($query),
            mb_substr($this->text($query, QueryParameter::Query), 0, self::MAX_QUERY_LENGTH),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, list<string>>
     */
    private function facets(Listing $listing, array $query): array
    {
        $selected = [];

        foreach ($listing->facets() as $facet) {
            $values = $this->values($query[$this->parameters->for($facet->taxonomy)] ?? null, $facet);

            if ($values !== []) {
                $selected[$facet->taxonomy] = $values;
            }
        }

        return $selected;
    }

    /**
     * Sorted and capped: one state must have one URL, and a crafted one must not
     * turn into thousands of filter clauses.
     *
     * @return list<string>
     */
    private function values(mixed $raw, Facet $facet): array
    {
        $values = explode(self::VALUE_SEPARATOR, is_string($raw) ? $raw : '');
        $values = array_values(array_unique(array_filter(array_map(trim(...), $values), strlen(...))));

        sort($values);

        return array_slice($values, 0, $facet->selection->allowsSeveralValues() ? $facet->cap : 1);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function sort(Listing $listing, array $query): ?string
    {
        $sort = $this->text($query, QueryParameter::Sort);

        return isset($listing->sorts()[$sort]) ? $sort : null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function text(array $query, QueryParameter $parameter): string
    {
        $value = $query[$this->parameters->reserved($parameter)] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(array $query): int
    {
        $page = (int) $this->text($query, QueryParameter::Page);

        return max($page, ListingState::FIRST_PAGE);
    }
}
