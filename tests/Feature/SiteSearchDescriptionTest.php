<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Listing\SearchableType;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\Preconnect;
use Modules\MeiliFacets\View\SiteSearchDescription;
use Tests\TestCase;

trait SiteSearchDescriptionSteps
{
    private const string ENGINE = 'https://engine.test:7700/multi-search';

    private function description(string $engine, UrlParameters $parameters = new UrlParameters([])): SiteSearchDescription
    {
        return new SiteSearchDescription(
            $this->app->make(CountLabel::class),
            new Preconnect(new BrowserConnection($engine, 'search', 'posts'), new ListingPage),
            $parameters,
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

// Runs here rather than standalone: the count pattern is translated and the locale is WordPress's.
uses(TestCase::class, SiteSearchDescriptionSteps::class);

it('describes a root in the shape the client reads', function (): void {
    $countLabel = $this->app->make(CountLabel::class);
    $root = new SearchRoot('header', new SearchSettings(3, 200, 6), [
        'product' => $this->type('product', 'Products', '/shop'),
        'post' => $this->type('post', 'Posts', null),
    ]);

    expect($this->description(self::ENGINE)->of($root))->toBe([
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
        'seeAllParameter' => 'q',
    ]);
});

it('warms no origin while the browser has no engine to reach', function (): void {
    $root = new SearchRoot('header', new SearchSettings, []);

    expect($this->description('')->of($root)['preconnect'])->toBe('');
});

// « See all » writes the term where the listing reads it, renamed or not.
it('names the parameter the listing reads the term from', function (): void {
    $root = new SearchRoot('header', new SearchSettings, []);
    $renamed = new UrlParameters([], [QueryParameter::Query->value => 'recherche']);

    expect($this->description(self::ENGINE, $renamed)->of($root)['seeAllParameter'])->toBe('recherche');
});
