<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\SiteSearch\SearchableType;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\Preconnect;
use Modules\MeiliFacets\View\SiteSearchDescription;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Runs here rather than standalone: the count pattern is translated and the locale is WordPress's. */
final class SiteSearchDescriptionTest extends TestCase
{
    private const string ENGINE = 'https://engine.test:7700/multi-search';

    #[Test]
    public function it_describes_a_root_in_the_shape_the_client_reads(): void
    {
        $countLabel = $this->app->make(CountLabel::class);
        $root = new SearchRoot('header', new SearchSettings(3, 200, 6), [
            'product' => $this->type('product', 'Products', '/shop'),
            'post' => $this->type('post', 'Posts', null),
        ]);

        $this->assertSame(
            [
                'name' => 'header',
                'minChars' => 3,
                'delay' => 200,
                'limit' => 6,
                'types' => [
                    $this->described('product', 'Products', '/shop'),
                    $this->described('post', 'Posts', null),
                ],
                'countPattern' => __(':count result|:count results'),
                'sectionPattern' => __(':heading: :count'),
                'locale' => $countLabel->languageTag(),
                'preconnect' => 'https://engine.test:7700',
            ],
            $this->description(self::ENGINE)->of($root)
        );
    }

    #[Test]
    public function it_warms_no_origin_while_the_browser_has_no_engine_to_reach(): void
    {
        $root = new SearchRoot('header', new SearchSettings, []);

        $this->assertSame('', $this->description('')->of($root)['preconnect']);
    }

    private function description(string $engine): SiteSearchDescription
    {
        return new SiteSearchDescription(
            $this->app->make(CountLabel::class),
            new Preconnect(new BrowserConnection($engine, 'search', 'posts'), new ListingPage),
        );
    }

    private function type(string $postType, string $heading, ?string $archive): SearchableType
    {
        return new SearchableType(
            $postType,
            $heading,
            "All {$heading}",
            ["post_type = \"{$postType}\""],
            ['post_title'],
            'meilifacets::search.card',
            $archive,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function described(string $postType, string $heading, ?string $archive): array
    {
        return [
            'postType' => $postType,
            'heading' => $heading,
            'seeAllLabel' => "All {$heading}",
            'baseFilter' => ["post_type = \"{$postType}\""],
            'searchOn' => ['post_title'],
            'archive' => $archive,
        ];
    }
}
