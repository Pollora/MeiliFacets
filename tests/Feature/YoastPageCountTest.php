<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Tests\Unit\Doubles\AnsweredListing;
use Modules\MeiliFacets\View\YoastPageCount;
use Modules\MeiliFacets\View\YoastPageWords;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** 16 cards a page: 40 results make 3 pages, 12 make one. Yoast counted 4 on WordPress's own query. */
final class YoastPageCountTest extends TestCase
{
    private const array YOAST = ['%%page%%' => '- Page 2 of 4', '%%pagetotal%%' => '4', '%%sep%%' => '-'];

    #[Test]
    public function it_leaves_yoast_alone_on_a_page_without_a_listing(): void
    {
        $count = new YoastPageCount(new ListingPage, new YoastPageWords);

        $this->assertSame(self::YOAST, $count->countTheEnginePages(self::YOAST));
    }

    #[Test]
    public function it_counts_the_pages_the_engine_answered(): void
    {
        $replacements = $this->countOn(page: 2, total: 40)->countTheEnginePages(self::YOAST);

        $this->assertSame(new YoastPageWords()->of(2, 3), $replacements['%%page%%']);
        $this->assertStringEndsWith('2 sur 3', $replacements['%%page%%']);
        $this->assertSame('3', $replacements['%%pagetotal%%']);
        $this->assertSame('-', $replacements['%%sep%%']);
    }

    #[Test]
    public function it_names_no_page_when_the_filters_leave_a_single_one(): void
    {
        $this->assertSame('', $this->countOn(page: 2, total: 12)->countTheEnginePages(self::YOAST)['%%page%%']);
    }

    #[Test]
    public function it_names_no_page_on_the_first(): void
    {
        $this->assertSame('', $this->countOn(page: 1, total: 40)->countTheEnginePages(self::YOAST)['%%page%%']);
    }

    #[Test]
    public function it_names_no_page_count_for_a_listing_left_empty(): void
    {
        $replacements = $this->countOn(page: 2, total: 0)->countTheEnginePages(self::YOAST);

        $this->assertSame(['', ''], [$replacements['%%page%%'], $replacements['%%pagetotal%%']]);
    }

    #[Test]
    public function it_adds_no_variable_the_text_does_not_hold(): void
    {
        $this->assertSame(['%%sep%%' => '-'], $this->countOn(page: 2, total: 40)->countTheEnginePages(['%%sep%%' => '-']));
    }

    #[Test]
    public function it_leaves_yoast_its_own_count_when_the_engine_did_not_answer(): void
    {
        Exceptions::fake();
        $listingPage = new ListingPage;
        $listingPage->markAsCurrent(AnsweredListing::unavailable());

        $this->assertSame(self::YOAST, new YoastPageCount($listingPage, new YoastPageWords)->countTheEnginePages(self::YOAST));
    }

    private function countOn(int $page, int $total): YoastPageCount
    {
        $listingPage = new ListingPage;
        $listingPage->markAsCurrent(AnsweredListing::withTotal($total, new ListingState(page: $page)));

        return new YoastPageCount($listingPage, new YoastPageWords);
    }
}
