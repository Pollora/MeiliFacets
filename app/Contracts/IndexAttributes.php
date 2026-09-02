<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

/**
 * Index attributes a plugin contributes on top of the ones the module declares.
 */
interface IndexAttributes
{
    /**
     * @return list<string>
     */
    public function filterable(): array;

    /**
     * @return list<string>
     */
    public function sortable(): array;
}
