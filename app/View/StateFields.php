<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Support\UrlParameters;

final readonly class StateFields
{
    public function __construct(private UrlParameters $parameters) {}

    /**
     * @return list<HiddenField>
     */
    public function of(ResolvedListing $listing): array
    {
        return [
            ...$this->facets($listing),
            ...$this->sort($listing),
            ...$this->price($listing),
        ];
    }

    /**
     * @return list<HiddenField>
     */
    private function facets(ResolvedListing $listing): array
    {
        $fields = [];

        foreach ($listing->facets() as $facet) {
            $selected = $listing->selectedIn($facet);

            if ($selected !== []) {
                $values = implode(StateReader::VALUE_SEPARATOR, $selected);
                $fields[] = new HiddenField($this->parameters->for($facet->taxonomy), $values);
            }
        }

        return $fields;
    }

    /**
     * @return list<HiddenField>
     */
    private function sort(ResolvedListing $listing): array
    {
        $sort = $listing->state()->sort;

        if ($sort === null) {
            return [];
        }

        return [new HiddenField($this->parameters->reserved(QueryParameter::Sort), $sort)];
    }

    /**
     * @return list<HiddenField>
     */
    private function price(ResolvedListing $listing): array
    {
        $fields = [];

        foreach (PriceBound::cases() as $bound) {
            $asked = $listing->state()->price->bound($bound);

            if ($asked !== null) {
                $fields[] = new HiddenField($this->parameters->reserved($bound->parameter()), Range::formatBound($asked));
            }
        }

        return $fields;
    }
}
