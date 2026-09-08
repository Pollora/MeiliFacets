<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Support\UrlParameters;

/** The shape is the PHP/JavaScript contract, written once here and once in `description.js`. */
final readonly class ListingDescription
{
    public function __construct(
        private UrlParameters $parameters,
        private EngineLimits $limits,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function of(ResolvedListing $listing): array
    {
        return [
            'name' => $listing->name(),
            'filter' => $listing->baseFilter(),
            'perPage' => $listing->perPage(),
            'reachableHits' => $this->limits->reachableHits,
            'attributes' => [DocumentField::Card->value],
            'apply' => $listing->applyMode()->value,
            'facets' => $this->facets($listing),
            'params' => $this->params($listing),
            'reserved' => $this->reserved(),
            'sorts' => $this->sorts($listing->sorts()),
            // The client has no catalogue of its own: the translated pattern travels with the description.
            'countPattern' => trans(':count result|:count results'),
            'filterPattern' => trans(':count active filter|:count active filters'),
            'foldLabels' => ['more' => trans('Show more'), 'less' => trans('Show less')],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facets(ResolvedListing $listing): array
    {
        return array_map(
            static fn (Facet $facet): array => [
                'taxonomy' => $facet->taxonomy,
                'multiple' => $facet->selection->allowsSeveralValues(),
                'cap' => $facet->cap,
                'visible' => $facet->visible,
            ],
            $listing->facets()
        );
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
}
