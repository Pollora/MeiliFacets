<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Listing\StateReader;

final readonly class StateFields
{
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
                $fields[] = new HiddenField($listing->parameterFor($facet->taxonomy), $values);
            }
        }

        return $fields;
    }

    /**
     * @return list<HiddenField>
     */
    private function sort(ResolvedListing $listing): array
    {
        $sort = $listing->currentSort();

        if ($sort === null) {
            return [];
        }

        return [new HiddenField($listing->parameterForReserved(QueryParameter::Sort), $sort)];
    }

    /**
     * @return list<HiddenField>
     */
    private function price(ResolvedListing $listing): array
    {
        $fields = [];

        foreach (PriceBound::cases() as $bound) {
            $asked = $bound->of($listing->askedPrice());

            if ($asked !== null) {
                $fields[] = new HiddenField($listing->parameterForReserved($bound->parameter()), Range::formatBound($asked));
            }
        }

        return $fields;
    }
}
