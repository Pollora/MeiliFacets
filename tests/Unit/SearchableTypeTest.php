<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\SiteSearch\NoFieldToSearch;
use Modules\MeiliFacets\SiteSearch\SearchableType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchableTypeTest extends TestCase
{
    #[Test]
    public function it_changes_what_a_project_corrects_and_leaves_the_rest(): void
    {
        $type = $this->type();

        $corrected = $type
            ->withHeading('News')
            ->withSeeAllLabel('All the news')
            ->withCard('theme::news-card')
            ->withSearchOn(['post_title']);

        $this->assertSame(
            ['News', 'All the news', 'theme::news-card', ['post_title']],
            [$corrected->heading, $corrected->seeAllLabel, $corrected->card, $corrected->searchOn]
        );
        $this->assertSame(
            [$type->postType, $type->baseFilter, $type->archive],
            [$corrected->postType, $corrected->baseFilter, $corrected->archive]
        );
        $this->assertSame('Posts', $type->heading);
    }

    #[Test]
    public function it_refuses_a_type_with_nothing_to_search_naming_it(): void
    {
        $this->expectException(NoFieldToSearch::class);
        $this->expectExceptionMessage('Post type "post" has no field to search');

        $this->type()->withSearchOn([]);
    }

    private function type(): SearchableType
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
}
