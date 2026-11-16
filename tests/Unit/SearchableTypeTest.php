<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\SearchableType;

use Modules\MeiliFacets\Listing\NoFieldToSearch;
use Modules\MeiliFacets\Listing\SearchableType;

function type(): SearchableType
{
    return new SearchableType(
        postType: 'post',
        heading: 'Posts',
        seeAllLabel: 'All posts',
        baseFilter: ['post_type = "post"'],
        searchOn: ['post_title', 'excerpt'],
        card: 'meilifacets::search.card',
        archive: 'https://example.test/news',
    );
}

it('changes what a project corrects and leaves the rest', function (): void {
    $type = type();

    $corrected = $type
        ->withHeading('News')
        ->withSeeAllLabel('All the news')
        ->withCard('theme::news-card')
        ->withSearchOn(['post_title']);

    expect([$corrected->heading, $corrected->seeAllLabel, $corrected->card, $corrected->searchOn])->toBe(['News', 'All the news', 'theme::news-card', ['post_title']]);
    expect([$corrected->postType, $corrected->baseFilter, $corrected->archive])->toBe([$type->postType, $type->baseFilter, $type->archive]);
    expect($type->heading)->toBe('Posts');
});

/** A project whose archive renders no listing sends « see all » elsewhere, or nowhere. */
it('leads see all where the project says', function (): void {
    $type = type();

    expect($type->withArchive('https://example.test/journal')->archive)->toBe('https://example.test/journal');
    expect($type->archive)->toBe('https://example.test/news');
});

it('leads see all nowhere without an archive', function (): void {
    $type = type();

    expect($type->withoutArchive()->archive)->toBeNull();
    expect($type->withoutArchive()->searchOn)->toBe($type->searchOn);
});

it('refuses a type with nothing to search naming it', function (): void {
    $this->expectException(NoFieldToSearch::class);
    $this->expectExceptionMessage('Post type "post" has no field to search');

    type()->withSearchOn([]);
});
