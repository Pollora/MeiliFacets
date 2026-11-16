<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Support\UrlParameters;

beforeEach(function (): void {
    $this->policy = new IndexingPolicy(new UrlParameters(['product_brand' => 'marque']), new ListingPage);
});

it('keeps a secondary view out of the index and its links followed', function (): void {
    $robots = $this->policy->asSecondaryView(['index' => true, 'max-image-preview' => 'large']);

    expect($robots)->toBe(['max-image-preview' => 'large', 'noindex' => true, 'follow' => true]);
});

it('leaves a bare path indexable', function (): void {
    expect($this->policy->appliesTo([]))->toBeFalse();
});

it('covers a mapped facet', function (): void {
    expect($this->policy->appliesTo(['marque' => 'acme']))->toBeTrue();
});

it('covers a facet left unmapped', function (): void {
    expect($this->policy->appliesTo(['f_product_cat' => 'coats']))->toBeTrue();
});

// Nothing links to page 2 since the controls became buttons: it is a duplicate with no reader.
it('covers a page number', function (): void {
    expect($this->policy->appliesTo(['pg' => '2']))->toBeTrue();
});

// The same products in another order, and a search result page.
it('covers a sort and a search', function (): void {
    expect($this->policy->appliesTo(['sort' => 'price_asc']))->toBeTrue();
    expect($this->policy->appliesTo(['q' => 'bonjour']))->toBeTrue();
});

// A project may rename the reserved parameters; the policy follows the mapping, not the default.
it('follows a renamed reserved parameter', function (): void {
    $policy = new IndexingPolicy(new UrlParameters([], ['q' => 'recherche']), new ListingPage);

    expect($policy->appliesTo(['recherche' => 'bonjour']))->toBeTrue();
    expect($policy->appliesTo(['q' => 'bonjour']))->toBeFalse();
});

it('ignores a parameter that belongs to nothing', function (): void {
    expect($this->policy->appliesTo(['utm_source' => 'newsletter']))->toBeFalse();
});

it('ignores a facet left empty', function (): void {
    expect($this->policy->appliesTo(['marque' => '']))->toBeFalse();
    expect($this->policy->appliesTo(['marque' => '   ']))->toBeFalse();
    expect($this->policy->appliesTo(['marque' => ['acme']]))->toBeFalse();
});
