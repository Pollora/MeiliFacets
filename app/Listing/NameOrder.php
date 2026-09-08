<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Collator;
use Modules\MeiliFacets\Contracts\ValueOrder;

final readonly class NameOrder implements ValueOrder
{
    public function __construct(private ?Collator $collator = null) {}

    public function compare(FacetValue $first, FacetValue $second): int
    {
        // `Collator::compare()` returns `false` on a string that is not valid UTF-8.
        $order = $this->collator?->compare($first->label, $second->label);

        return is_int($order) ? $order : strnatcasecmp($first->label, $second->label);
    }
}
