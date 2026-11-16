<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use LogicException;
use Modules\MeiliFacets\Listing\SearchableType;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\SiteSearch\SearchTypeRefused;
use Modules\MeiliFacets\Support\NameRefused;

function searchRegistryRoot(string $name): SearchRoot
{
    return new SearchRoot($name, new SearchSettings, []);
}

function searchRegistryType(string $postType): SearchableType
{
    return new SearchableType($postType, ucfirst($postType), 'All', [], ['post_title'], 'card', null);
}

it('keeps roots of different names apart', function (): void {
    $registry = new SearchRegistry;
    $header = searchRegistryRoot('header');
    $footer = searchRegistryRoot('footer');
    $registry->add($header);
    $registry->add($footer);

    expect([$registry->named('header'), $registry->named('footer')])->toBe([$header, $footer]);
});

it('hands back the only root rendered', function (): void {
    $registry = new SearchRegistry;
    $registry->add(searchRegistryRoot('header'));

    expect($registry->onlyOne()->name)->toBe('header');
});

it('refuses a second root under the same name', function (): void {
    $registry = new SearchRegistry;
    $registry->add(searchRegistryRoot('search'));

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('A search named "search" is already rendered');

    $registry->add(searchRegistryRoot('search'));
});

it('names the candidates when a brick names no root', function (): void {
    $registry = new SearchRegistry;
    $registry->add(searchRegistryRoot('header'));
    $registry->add(searchRegistryRoot('footer'));

    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('Name the search: 2 are declared (header, footer)');

    $registry->onlyOne();
});

it('names the roots it knows when asked for another', function (): void {
    $registry = new SearchRegistry;
    $registry->add(searchRegistryRoot('header'));

    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('No search named "footer". Declared: header.');

    $registry->named('footer');
});

it('refuses a second section of one type in one root', function (): void {
    $registry = new SearchRegistry;
    $header = searchRegistryRoot('header');
    $registry->placeSection($header, searchRegistryType('post'));
    $registry->placeSection($header, searchRegistryType('product'));

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('The search "header" already holds a section for "post": place one section per type.');

    $registry->placeSection($header, searchRegistryType('post'));
});

it('lets each root hold its own section of a type', function (): void {
    $registry = new SearchRegistry;
    $registry->placeSection(searchRegistryRoot('header'), searchRegistryType('post'));
    $registry->placeSection(searchRegistryRoot('footer'), searchRegistryType('post'));

    $this->addToAssertionCount(1);
});

it('hands a section the type its root accepts', function (): void {
    $post = searchRegistryType('post');

    expect(new SearchRoot('header', new SearchSettings, ['post' => $post])->type('post'))->toBe($post);
});

it('refuses a section a type its root does not accept naming those it does', function (): void {
    $root = new SearchRoot('header', new SearchSettings, ['product' => searchRegistryType('product'), 'post' => searchRegistryType('post')]);

    $this->expectException(SearchTypeRefused::class);
    $this->expectExceptionMessage('Post type "page" is not searchable: declare it in SearchableTypes and index it in MeiliScout. Searchable here: product, post.');

    $root->type('page');
});
