<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Tests\Unit\Doubles\AnsweredListing;
use Modules\MeiliFacets\View\YoastPageCount;
use Modules\MeiliFacets\View\YoastPageWords;
use Tests\TestCase;

trait YoastPageCountSupport
{
    private const array YOAST = ['%%page%%' => '- Page 2 of 4', '%%pagetotal%%' => '4', '%%sep%%' => '-'];

    private function countOn(int $page, int $total): YoastPageCount
    {
        $listingPage = new ListingPage;
        $listingPage->markAsCurrent(AnsweredListing::withTotal($total, new ListingState(page: $page)));

        return new YoastPageCount($listingPage, new YoastPageWords);
    }
}

/** 16 cards a page: 40 results make 3 pages, 12 make one. Yoast counted 4 on WordPress's own query. */
uses(TestCase::class, YoastPageCountSupport::class);

it('leaves yoast alone on a page without a listing', function (): void {
    $count = new YoastPageCount(new ListingPage, new YoastPageWords);

    expect($count->countTheEnginePages(self::YOAST))->toBe(self::YOAST);
});

it('counts the pages the engine answered', function (): void {
    $replacements = $this->countOn(page: 2, total: 40)->countTheEnginePages(self::YOAST);

    expect($replacements['%%page%%'])->toBe(new YoastPageWords()->of(2, 3));
    expect($replacements['%%page%%'])->toEndWith('2 sur 3');
    expect($replacements['%%pagetotal%%'])->toBe('3');
    expect($replacements['%%sep%%'])->toBe('-');
});

it('names no page when the filters leave a single one', function (): void {
    expect($this->countOn(page: 2, total: 12)->countTheEnginePages(self::YOAST)['%%page%%'])->toBe('');
});

it('names no page on the first', function (): void {
    expect($this->countOn(page: 1, total: 40)->countTheEnginePages(self::YOAST)['%%page%%'])->toBe('');
});

it('names no page count for a listing left empty', function (): void {
    $replacements = $this->countOn(page: 2, total: 0)->countTheEnginePages(self::YOAST);

    expect([$replacements['%%page%%'], $replacements['%%pagetotal%%']])->toBe(['', '']);
});

it('adds no variable the text does not hold', function (): void {
    expect($this->countOn(page: 2, total: 40)->countTheEnginePages(['%%sep%%' => '-']))->toBe(['%%sep%%' => '-']);
});

it('leaves yoast its own count when the engine did not answer', function (): void {
    Exceptions::fake();
    $listingPage = new ListingPage;
    $listingPage->markAsCurrent(AnsweredListing::unavailable());

    expect(new YoastPageCount($listingPage, new YoastPageWords)->countTheEnginePages(self::YOAST))->toBe(self::YOAST);
});
