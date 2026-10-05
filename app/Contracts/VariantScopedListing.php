<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Listing\SearchScope;

/** A listing whose items have a document per variant, which a filter on a variant reads instead of the item's. */
interface VariantScopedListing extends SearchScopedListing
{
    /**
     * @return list<string>
     */
    public function variantTaxonomies(): array;

    /**
     * What browsing reads on the documents of the variants, and of every item without any.
     *
     * @return list<string>
     */
    public function variantFilter(): array;

    /** What a search reads on the documents of the variants, and of every item without any. */
    public function variantSearchScope(): SearchScope;
}
