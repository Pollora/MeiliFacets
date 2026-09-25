<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface SearchableAttributes
{
    /**
     * Every field the index searches, most important first. A field left out cannot be searched at all.
     *
     * @return list<string>
     */
    public function all(): array;
}
