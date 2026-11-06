<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Http\Request;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\AnsweredListing;
use Modules\MeiliFacets\View\ListingRobots;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListingRobotsTest extends TestCase
{
    #[Test]
    public function the_bare_view_keeps_what_wordpress_prints_and_any_other_is_kept_out_of_the_index(): void
    {
        $robots = $this->robotsOn(new ListingPage)->byView();

        $this->assertNotNull($robots);
        $this->assertStringNotContainsString('noindex', $robots['bare']);
        $this->assertSame('noindex, follow', $robots['secondary']);
    }

    #[Test]
    public function a_secondary_view_tells_the_bare_one_too(): void
    {
        $this->requesting('/shop/page/2?marque=acme');
        $page = new ListingPage;
        $page->markAsCurrent(AnsweredListing::withTotal(40));

        $robots = $this->robotsOn($page)->byView();

        $this->assertNotNull($robots);
        $this->assertStringNotContainsString('noindex', $robots['bare']);
        $this->assertSame('noindex, follow', $robots['secondary']);
    }

    #[Test]
    public function the_bare_view_is_told_even_when_the_project_marks_every_page_as_a_listing(): void
    {
        $everyPage = static fn (): bool => true;
        add_filter(ListingPage::FILTER, $everyPage);
        $this->requesting('/shop?marque=acme');

        try {
            $robots = $this->robotsOn(new ListingPage)->byView();
        } finally {
            remove_filter(ListingPage::FILTER, $everyPage);
        }

        $this->assertStringNotContainsString('noindex', $robots['bare'] ?? 'noindex');
    }

    #[Test]
    public function nothing_is_told_when_the_head_prints_no_robots(): void
    {
        remove_action('wp_head', 'wp_robots', 1);

        try {
            $this->assertNull($this->robotsOn(new ListingPage)->byView());
        } finally {
            add_action('wp_head', 'wp_robots', 1);
        }
    }

    private function robotsOn(ListingPage $page): ListingRobots
    {
        return new ListingRobots(new IndexingPolicy(new UrlParameters(['product_brand' => 'marque']), $page));
    }

    private function requesting(string $uri): void
    {
        $this->app->instance('request', Request::create($uri));
    }
}
