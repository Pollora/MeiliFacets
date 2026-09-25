<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\ActiveValueKind;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\Facet;
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
        $patterns = ActiveValuePatterns::translated();
        $ticked = array_map(fn (Facet $facet): array => $this->tickedIn($listing, $facet, $patterns), $listing->facets());

        return [...array_merge(...$ticked), ...$this->priced($listing, $patterns)];
    }

    /** Written once here and read by the client, which fills the placeholders itself. */
    public function patterns(): array
    {
        return ActiveValuePatterns::translated()->toArray();
    }

    /**
     * A slug the page rendered no label for gets no pill: printed as the URL wrote
     * it, anyone could write on the page.
     *
     * @return list<ActiveValue>
     */
    private function tickedIn(ResolvedListing $listing, Facet $facet, ActiveValuePatterns $patterns): array
    {
        $labels = $listing->labelsOf($facet);
        $parameter = $listing->parameterFor($facet->taxonomy);
        $labelled = array_intersect($listing->selectedIn($facet), array_keys($labels));

        return array_map(
            fn (string $slug): ActiveValue => $this->removableTerm($labels[$slug], $parameter, $slug, $patterns),
            array_values($labelled),
        );
    }

    /**
     * @return list<ActiveValue>
     */
    private function priced(ResolvedListing $listing, ActiveValuePatterns $patterns): array
    {
        $price = $listing->askedPrice();

        if ($price->isEmpty()) {
            return [];
        }

        $label = $patterns->range($price, $this->money->of($price->min), $this->money->of($price->max));

        return [new ActiveValue(
            label: $label,
            parameter: $listing->parameterForReserved(QueryParameter::MinPrice),
            value: '',
            action: $patterns->removal($label),
            kind: ActiveValueKind::Price,
        )];
    }

    private function removableTerm(string $label, string $parameter, string $value, ActiveValuePatterns $patterns): ActiveValue
    {
        return new ActiveValue($label, $parameter, $value, $patterns->removal($label), ActiveValueKind::Term);
    }
}
