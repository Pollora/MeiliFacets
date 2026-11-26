<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\RequestSharedState;

use Modules\MeiliFacets\Contracts\DefaultTerms;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductOrderingChanges;
use Modules\MeiliFacets\Indexing\WooCommerce\VariationChanges;
use Modules\MeiliFacets\Indexing\WooCommerce\VariationMetaRewrites;
use Modules\MeiliFacets\Providers\IndexingServiceProvider;
use Modules\MeiliFacets\Providers\ListingServiceProvider;
use Modules\MeiliFacets\Providers\RenderingServiceProvider;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;

beforeEach(function (): void {
    WordPress::boot();
});

/**
 * Hooks fire on one instance and are read on the next: without a shared one, what a request remembered is lost, and
 * every component of a page searches the engine again.
 */
it('shares for the request what carries state between hooks or components', function (string $provider, string $abstract): void {
    new $provider(app())->register();

    expect(app()->isShared($abstract))->toBeTrue();
})->with([
    [IndexingServiceProvider::class, VariationChanges::class],
    [IndexingServiceProvider::class, VariationMetaRewrites::class],
    [IndexingServiceProvider::class, ProductOrderingChanges::class],
    [IndexingServiceProvider::class, IndexedTaxonomies::class],
    [IndexingServiceProvider::class, FacetedPostIndexable::class],
    [RenderingServiceProvider::class, CurrentListing::class],
    [RenderingServiceProvider::class, ListingPage::class],
    [ListingServiceProvider::class, DefaultTerms::class],
]);
