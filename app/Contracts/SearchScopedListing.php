<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Listing\SearchScope;

/** A listing whose search does not read what its browsing reads. */
interface SearchScopedListing extends Listing
{
    public function searchScope(): SearchScope;
}
