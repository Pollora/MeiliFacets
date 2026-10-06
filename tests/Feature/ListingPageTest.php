<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListingPageTest extends TestCase
{
    /** The suite shares one application: a listing marked here would reach a later class. */
    protected function tearDown(): void
    {
        remove_all_filters(ListingPage::FILTER);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function a_page_that_renders_no_listing_is_not_a_listing_page(): void
    {
        $this->assertFalse($this->app->make(ListingPage::class)->isCurrent());
    }

    /** Layouts render their sections first, as `@extends` does. */
    #[Test]
    public function the_head_of_a_layout_rendering_a_listing_knows_it(): void
    {
        $page = Blade::render(<<<'BLADE'
            @section('content')<x-meilifacets::listing />@endsection
            <head>{{ app(\Modules\MeiliFacets\Http\ListingPage::class)->isCurrent() ? 'listing' : 'none' }}</head>
            <body>@yield('content')</body>
            BLADE);

        $this->assertStringContainsString('<head>listing</head>', $page);
    }

    #[Test]
    public function a_project_can_declare_a_listing_page_the_module_cannot_see(): void
    {
        add_filter(ListingPage::FILTER, '__return_true');

        $this->assertTrue($this->app->make(ListingPage::class)->isCurrent());
    }

    #[Test]
    public function a_project_can_withdraw_a_page_that_renders_a_listing(): void
    {
        $page = $this->app->make(ListingPage::class);
        $page->markAsCurrent();

        add_filter(ListingPage::FILTER, '__return_false');

        $this->assertFalse($page->isCurrent());
    }

    #[Test]
    public function a_page_number_on_a_page_without_a_listing_leaves_its_robots_alone(): void
    {
        $this->withQuery(['pg' => '3']);

        $robots = $this->app->make(IndexingPolicy::class)->noindexSecondaryViews(['index' => true]);

        $this->assertSame(['index' => true], $robots);
    }

    #[Test]
    public function a_page_number_on_a_listing_page_is_kept_out_of_the_index(): void
    {
        $this->withQuery(['pg' => '3']);
        $this->app->make(ListingPage::class)->markAsCurrent();

        $robots = $this->app->make(IndexingPolicy::class)->noindexSecondaryViews(['index' => true]);

        $this->assertSame(['noindex' => true, 'follow' => true], $robots);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function withQuery(array $query): void
    {
        $this->app->instance('request', Request::create('/', 'GET', $query));
    }
}
