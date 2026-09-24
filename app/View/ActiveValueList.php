<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Support\Money;

/** The pills of a listing, built from its state: the ticked values in facet order, then the price range. */
final readonly class ActiveValueList
{
    public function __construct(private Money $money) {}

    /**
     * @return list<ActiveValue>
     */
    public function of(ResolvedListing $listing): array
    {
        $ticked = array_map(fn (Facet $facet): array => $this->tickedIn($listing, $facet), $listing->facets());

        return [...array_merge(...$ticked), ...$this->priced($listing)];
    }

    /**
     * Written once here and read by the client, which fills the placeholders itself.
     *
     * @return array{remove: string, between: string, from: string, upTo: string}
     */
    public function patterns(): array
    {
        return [
            'remove' => __('Remove the :label filter'),
            'between' => __(':min – :max'),
            'from' => __('From :min'),
            'upTo' => __('Up to :max'),
        ];
    }

    /**
     * A slug the page rendered no label for gets no pill: printed as the URL wrote
     * it, anyone could write on the page.
     *
     * @return list<ActiveValue>
     */
    private function tickedIn(ResolvedListing $listing, Facet $facet): array
    {
        $labels = $listing->labelsOf($facet);
        $parameter = $listing->parameterFor($facet->taxonomy);
        $labelled = array_intersect($listing->state()->selected($facet->taxonomy), array_keys($labels));

        return array_map(
            fn (string $slug): ActiveValue => $this->removable($labels[$slug], $parameter, $slug),
            array_values($labelled),
        );
    }

    /**
     * @return list<ActiveValue>
     */
    private function priced(ResolvedListing $listing): array
    {
        $price = $listing->state()->price;

        if ($price->isEmpty()) {
            return [];
        }

        return [$this->removable($this->priceLabel($price), $listing->parameterForReserved(QueryParameter::MinPrice), '')];
    }

    private function priceLabel(Range $price): string
    {
        $patterns = $this->patterns();
        $written = [':min' => $this->money->of($price->min), ':max' => $this->money->of($price->max)];

        if ($price->max === null) {
            return strtr($patterns['from'], $written);
        }

        if ($price->min === null) {
            return strtr($patterns['upTo'], $written);
        }

        return strtr($patterns['between'], $written);
    }

    private function removable(string $label, string $parameter, string $value): ActiveValue
    {
        return new ActiveValue($label, $parameter, $value, strtr($this->patterns()['remove'], [':label' => $label]));
    }
}
