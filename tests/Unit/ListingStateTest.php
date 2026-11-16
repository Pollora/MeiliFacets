<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Sort;

it('hands back the sort the state names', function (): void {
    $newest = new Sort('New arrivals', ['post_date:desc']);

    expect(new ListingState(sort: 'newest')->chosenSort(['newest' => $newest]))->toBe($newest);
});

it('chooses no sort while none is named', function (): void {
    expect(new ListingState()->chosenSort(['' => new Sort('Unnamed', ['post_date:desc'])]))->toBeNull();
});

it('chooses no sort the listing does not offer', function (): void {
    expect(new ListingState(sort: 'rating')->chosenSort(['newest' => new Sort('New arrivals', ['post_date:desc'])]))->toBeNull();
});

it('selects any of the taxonomies one of its facets names', function (): void {
    expect(new ListingState(['pa_volume' => ['400ml']])->selectsAnyOf(['pa_scent', 'pa_volume']))->toBeTrue();
});

it('selects none of the taxonomies no facet names', function (): void {
    expect(new ListingState(['product_brand' => ['acme']])->selectsAnyOf(['pa_volume']))->toBeFalse();
    expect(new ListingState()->selectsAnyOf(['pa_volume']))->toBeFalse();
});
