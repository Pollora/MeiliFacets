<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use Modules\MeiliFacets\Contracts\SearchableAttributes;

/** The engine refuses a whole query that names one field outside `searchableAttributes`. */
final readonly class AttributesToSearchOn
{
    public function __construct(private SearchableAttributes $order) {}

    /**
     * @param  list<string>  $wanted
     * @return list<string> in the order the index ranks them
     */
    public function among(array $wanted): array
    {
        return array_values(array_intersect($this->order->all(), $wanted));
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    public function outside(array $fields): array
    {
        return array_values(array_diff($fields, $this->order->all()));
    }
}
