<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Closure;
use Collator;
use Modules\MeiliFacets\Contracts\ValueOrder;

final readonly class NameOrder implements ValueOrder
{
    /**
     * @param  (Closure(): ?Collator)|null  $currentCollator
     */
    public function __construct(private ?Closure $currentCollator = null) {}

    public function compare(FacetValue $first, FacetValue $second): int
    {
        $collator = $this->currentCollator instanceof Closure ? ($this->currentCollator)() : null;

        // `Collator::compare()` returns `false` on a string that is not valid UTF-8.
        $order = $collator?->compare($first->label, $second->label);

        return is_int($order) ? $order : strnatcasecmp($first->label, $second->label);
    }
}
