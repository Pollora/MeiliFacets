<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\TermScope;

/**
 * A facet of a hierarchical taxonomy that offers the level the visitor is on:
 * the terms directly under the one the path carries, and nothing else. A flat
 * list of a hundred terms across three levels is unreadable at any cap; a dozen
 * siblings are not.
 *
 * On a term with no children it shows nothing — moving sideways is the job of a
 * breadcrumb, not of a control that otherwise narrows.
 */
final readonly class ChildTermsFacet extends Facet
{
    /**
     * @param  array<string, int>  $distribution
     * @return array<string, int>
     */
    public function within(array $distribution, TermScope $scope): array
    {
        return array_intersect_key($distribution, array_flip($scope->childrenOf($this->taxonomy)));
    }
}
