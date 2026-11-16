<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Http\Request;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\AnsweredListing;
use Modules\MeiliFacets\View\ListingRobots;
use Tests\TestCase;

trait ListingRobotsSupport
{
    private function robotsOn(ListingPage $page): ListingRobots
    {
        return new ListingRobots(new IndexingPolicy(new UrlParameters(['product_brand' => 'marque']), $page));
    }

    private function requesting(string $uri): void
    {
        $this->app->instance('request', Request::create($uri));
    }
}

uses(TestCase::class, ListingRobotsSupport::class);

test('the bare view keeps what wordpress prints and any other is kept out of the index', function (): void {
    $robots = $this->robotsOn(new ListingPage)->byView();

    expect($robots)->not->toBeNull();
    $this->assertStringNotContainsString('noindex', $robots['bare']);
    expect($robots['secondary'])->toBe('noindex, follow');
});

test('a secondary view tells the bare one too', function (): void {
    $this->requesting('/shop/page/2?marque=acme');
    $page = new ListingPage;
    $page->markAsCurrent(AnsweredListing::withTotal(40));

    $robots = $this->robotsOn($page)->byView();

    expect($robots)->not->toBeNull();
    $this->assertStringNotContainsString('noindex', $robots['bare']);
    expect($robots['secondary'])->toBe('noindex, follow');
});

test('the bare view is told even when the project marks every page as a listing', function (): void {
    $everyPage = static fn (): bool => true;
    add_filter(ListingPage::FILTER, $everyPage);
    $this->requesting('/shop?marque=acme');

    try {
        $robots = $this->robotsOn(new ListingPage)->byView();
    } finally {
        remove_filter(ListingPage::FILTER, $everyPage);
    }

    $this->assertStringNotContainsString('noindex', $robots['bare'] ?? 'noindex');
});

test('nothing is told when the head prints no robots', function (): void {
    remove_action('wp_head', 'wp_robots', 1);

    try {
        expect($this->robotsOn(new ListingPage)->byView())->toBeNull();
    } finally {
        add_action('wp_head', 'wp_robots', 1);
    }
});
