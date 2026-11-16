<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Tests\Feature\Concerns\RequestsAnAddress;
use Tests\TestCase;

trait PageAddressHelpers
{
    /**
     * @param  list<string>  $owned
     */
    private function keptFrom(string $queryString, array $owned = []): string
    {
        request()->server->set('QUERY_STRING', $queryString);

        return $this->page()->queryWithout($owned);
    }

    private function linkFrom(string $path, string $queryString, int $page): string
    {
        $this->requesting($path.'?'.$queryString);
        request()->server->set('QUERY_STRING', $queryString);

        return $this->page()->pathOf($page).$this->page()->linkedQuery();
    }

    private function pathOf(string $requestUri): string
    {
        $this->requesting($requestUri);

        return $this->page()->path();
    }

    private function page(): PageAddress
    {
        return $this->app->make(PageAddress::class);
    }
}

uses(TestCase::class, RequestsAnAddress::class, PageAddressHelpers::class);

/** The suite shares one application: a query left behind would reach a later class. */
afterEach(function (): void {
    request()->query->replace([]);
    request()->server->remove('QUERY_STRING');
});

it('writes the first page under whatever name wordpress paginates', function (): void {
    $this->requesting('/boutique/seite/2?marque=globex', 'seite');

    expect($this->page()->path())->toBe('/boutique');
});

it('writes where the number of a later page goes', function (): void {
    $this->requesting('/boutique/page/3?marque=globex');
    expect($this->page()->pathPattern())->toBe('/boutique/page/:page');

    $this->requesting('/boutique?marque=globex', 'seite');
    expect($this->page()->pathPattern())->toBe('/boutique/seite/:page');
});

it('links a page in the path the listing state in the query', function (): void {
    expect($this->linkFrom('/boutique/page/3', 'marque=globex&s=creme', 2))->toBe('/boutique/page/2?marque=globex&s=creme');
    expect($this->linkFrom('/boutique/page/3', 'marque=globex', 1))->toBe('/boutique?marque=globex');
});

/** Repeated on every page, `add-to-cart` would fill the basket of whoever follows the links. */
it('links without what neither the listing nor wordpress reads', function (): void {
    expect($this->linkFrom('/boutique', 'utm_source=news&f_product_cat=visage&add-to-cart=12', 2))->toBe('/boutique/page/2?f_product_cat=visage');
});

it('links without a page number the path already carries', function (): void {
    expect($this->linkFrom('/boutique', 'pg=5&paged=4&marque=globex', 2))->toBe('/boutique/page/2?marque=globex');
});

it('writes a path a browser cannot read as a host', function (): void {
    expect($this->pathOf('//boutique/page/2'))->toBe('/boutique');
    expect($this->pathOf('//?s=creme'))->toBe('/');
});

it('keeps only what wordpress reads', function (): void {
    expect($this->keptFrom('s=creme&add-to-cart=12&post_type=product&utm_source=news'))->toBe('s=creme&post_type=product');
});

it('drops the page wordpress would read instead of the listing', function (): void {
    expect($this->keptFrom('paged=2&s=creme'))->toBe('s=creme');
});

it('drops what the listing writes itself', function (): void {
    expect($this->keptFrom('s=creme&post_type=product', ['s']))->toBe('post_type=product');
});

it('keeps each pair as php received it not as laravel left it', function (): void {
    request()->server->set('QUERY_STRING', 's=&post_type=%20product%20');
    request()->query->replace(['s' => null, 'post_type' => 'product']);

    expect($this->page()->queryWithout([]))->toBe('s=&post_type=%20product%20');
});

it('keeps a search written without a value and every repeat', function (): void {
    expect($this->keptFrom('s&s=a&utm_source=x&s=b'))->toBe('s&s=a&s=b');
});

it('reads a name as php hands it to wordpress', function (): void {
    expect($this->keptFrom('post_type%5B%5D=product&post.type=page&%FF=1'))->toBe('post_type%5B%5D=product&post.type=page');
});
