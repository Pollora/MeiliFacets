<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Tests\Unit\Doubles\AnsweredListing;
use Tests\TestCase;

trait ListingPageSupport
{
    /**
     * @param  array<string, string>  $query
     */
    private function withQuery(array $query): void
    {
        $this->app->instance('request', Request::create('/', 'GET', $query));
    }
}

uses(TestCase::class, ListingPageSupport::class);

afterEach(function (): void {
    remove_all_filters(ListingPage::FILTER);
    $this->app->forgetScopedInstances();
});

test('a page that renders no listing is not a listing page', function (): void {
    expect($this->app->make(ListingPage::class)->isCurrent())->toBeFalse();
});

/** Layouts render their sections first, as `@extends` does. */
test('the head of a layout rendering a listing knows it', function (): void {
    $page = Blade::render(<<<'BLADE'
        @section('content')<x-meilifacets::listing name="products" />@endsection
        <head>{{ app(\Modules\MeiliFacets\Http\ListingPage::class)->isCurrent() ? 'listing' : 'none' }}</head>
        <body>@yield('content')</body>
        BLADE);

    expect($page)->toContain('<head>listing</head>');
});

test('a project can declare a listing page the module cannot see', function (): void {
    add_filter(ListingPage::FILTER, '__return_true');

    expect($this->app->make(ListingPage::class)->isCurrent())->toBeTrue();
});

test('a project can withdraw a page that renders a listing', function (): void {
    $page = $this->app->make(ListingPage::class);
    $page->markAsCurrent(AnsweredListing::withTotal());

    add_filter(ListingPage::FILTER, '__return_false');

    expect($page->isCurrent())->toBeFalse();
});

test('a page number on a page without a listing leaves its robots alone', function (): void {
    $this->withQuery(['pg' => '3']);

    $robots = $this->app->make(IndexingPolicy::class)->noindexSecondaryViews(['index' => true]);

    expect($robots)->toBe(['index' => true]);
});

test('a page number on a listing page is kept out of the index', function (): void {
    $this->withQuery(['pg' => '3']);
    $this->app->make(ListingPage::class)->markAsCurrent(AnsweredListing::withTotal());

    $robots = $this->app->make(IndexingPolicy::class)->noindexSecondaryViews(['index' => true]);

    expect($robots)->toBe(['noindex' => true, 'follow' => true]);
});

test('the first listing rendered paginates the page', function (): void {
    $page = new ListingPage;
    $first = AnsweredListing::withTotal(40);
    $second = AnsweredListing::withTotal(12);

    $page->markAsCurrent($first);
    $page->markAsCurrent($second);

    expect([$page->isPaginatedBy($first), $page->isPaginatedBy($second), $page->pagination()?->pages()])->toBe([true, false, 3]);
});
