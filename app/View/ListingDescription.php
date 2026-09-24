<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Support\UrlParameters;

/** The shape is the PHP/JavaScript contract, written once here and once in `description.ts`. */
final readonly class ListingDescription
{
    public function __construct(
        private UrlParameters $parameters,
        private EngineLimits $limits,
        private Money $money,
        private PageAddress $page,
        private CountLabel $countLabel,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function of(ResolvedListing $listing): array
    {
        $params = $this->params($listing);
        $reserved = $this->reserved();

        return [
            'name' => $listing->name(),
            'filter' => $listing->baseFilter(),
            'baseQuery' => $listing->baseQuery(),
            'perPage' => $listing->perPage(),
            'reachableHits' => $this->limits->reachableHits,
            'attributes' => [DocumentField::Card->value],
            'apply' => $listing->applyMode()->value,
            'facets' => $this->facets($listing),
            'params' => $params,
            'reserved' => $reserved,
            'priceFields' => $this->priceFields($listing),
            'money' => $this->money->describe(),
            'sorts' => $this->sorts($listing->sorts()),
            'sortFilters' => (object) $this->sortFilters($listing->sorts()),
            'countPattern' => __(':count result|:count results'),
            'filterPattern' => __(':count active filter|:count active filters'),
            'locale' => $this->countLabel->languageTag(),
            'state' => $this->state($listing->state()),
            'pagePath' => $this->page->path(),
            'pageQuery' => $this->page->queryWithout([...array_values($params), ...array_values($reserved)]),
        ];
    }

    /**
     * @return array{facets: object, query: string, sort: ?string, page: int, price: array{min: ?float, max: ?float}}
     */
    private function state(ListingState $state): array
    {
        return [
            // An empty array is written `[]`, which the client reads as a list, not as taxonomies.
            'facets' => (object) $state->facets,
            'query' => $state->query,
            'sort' => $state->sort,
            'page' => $state->page,
            'price' => ['min' => $state->price->min, 'max' => $state->price->max],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facets(ResolvedListing $listing): array
    {
        return array_map(
            fn (Facet $facet): array => [
                'taxonomy' => $facet->taxonomy,
                'multiple' => $facet->selection->allowsSeveralValues(),
                'cap' => $facet->cap,
                'visible' => $facet->visible,
                'counts' => (object) $this->countsOf($listing, $facet),
            ],
            $listing->facets()
        );
    }

    /**
     * @return array<string, int> slug to count, for the values the page renders
     */
    private function countsOf(ResolvedListing $listing, Facet $facet): array
    {
        return array_column(array_map(
            static fn (FacetValue $value): array => [$value->slug, $value->count],
            $listing->valuesOf($facet),
        ), 1, 0);
    }

    /**
     * @return array<string, string>
     */
    private function params(ResolvedListing $listing): array
    {
        $params = [];

        foreach ($listing->facets() as $facet) {
            $params[$facet->taxonomy] = $this->parameters->for($facet->taxonomy);
        }

        return $params;
    }

    /**
     * The fields a range filters on, or nothing when no listing declares one: the
     * client builds no clause it was not told about.
     *
     * @return array<string, string>|null
     */
    private function priceFields(ResolvedListing $listing): ?array
    {
        return PriceFilter::isDeclaredAmong($listing->filters())
            ? ['min' => PriceField::Min->path(), 'max' => PriceField::Max->path()]
            : null;
    }

    /**
     * @return array<string, string>
     */
    private function reserved(): array
    {
        $reserved = [];

        foreach (QueryParameter::cases() as $parameter) {
            $reserved[$parameter->key()] = $this->parameters->reserved($parameter);
        }

        return $reserved;
    }

    /**
     * @param  array<string, Sort>  $sorts
     * @return array<string, list<string>>
     */
    private function sorts(array $sorts): array
    {
        return array_map(static fn (Sort $sort): array => $sort->expressions, $sorts);
    }

    /**
     * @param  array<string, Sort>  $sorts
     * @return array<string, array<string, string>>
     */
    private function sortFilters(array $sorts): array
    {
        return array_map(static fn (SortFilter $filter): array => $filter->describe(), SortFilter::carriedBy($sorts));
    }
}
