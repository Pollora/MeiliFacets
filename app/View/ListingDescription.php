<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Support\UrlParameters;

/**
 * What the server tells the browser about a listing, so that the same state
 * produces the same searches on both sides. Its shape is the contract, written
 * once here and once in `description.js`.
 */
final readonly class ListingDescription
{
    public function __construct(private UrlParameters $parameters) {}

    /**
     * @return array<string, mixed>
     */
    public function of(ResolvedListing $listing): array
    {
        return [
            'name' => $listing->name(),
            'filter' => $listing->baseFilter(),
            'perPage' => $listing->perPage(),
            'attributes' => [DocumentField::Card->value],
            'apply' => $listing->applyMode()->value,
            'facets' => $this->facets($listing),
            'params' => $this->params($listing),
            'reserved' => $this->reserved(),
            'sorts' => $this->sorts($listing->sorts()),
            // The client counts what the engine returns and has no catalogue of
            // its own: the translated pattern travels with the description.
            'countPattern' => trans(':count result|:count results'),
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
