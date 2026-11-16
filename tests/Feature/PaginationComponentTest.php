<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Listing\Pagination;
use Modules\MeiliFacets\Tests\Feature\Concerns\RequestsAnAddress;
use Tests\TestCase;

trait PaginationComponentSteps
{
    private function rendered(Pagination $pagination, string $queryString = 'marque=globex'): string
    {
        $this->requesting('/boutique/page/2?'.$queryString);
        request()->server->set('QUERY_STRING', $queryString);
        $address = $this->app->make(PageAddress::class);

        return (string) view('meilifacets::components.listing.pagination', [
            'pagination' => $pagination,
            'linkTo' => fn (int $page): string => $address->pathOf($page).$address->linkedQuery(),
            'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
            'scrollMark' => fn (): HtmlString => new HtmlString(''),
        ])->render();
    }

    /**
     * @return list<string>
     */
    private function hrefsIn(string $html): array
    {
        preg_match_all('/href="([^"]*)"/', $html, $matches);

        return array_map(html_entity_decode(...), $matches[1]);
    }
}

uses(TestCase::class, RequestsAnAddress::class, PaginationComponentSteps::class);

beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->server->remove('QUERY_STRING');
    $this->app->forgetScopedInstances();
});

it('renders its links from the listing it belongs to', function (): void {
    $this->requesting('/boutique');

    $html = Blade::render('<x-meilifacets::listing.pagination name="products" />');

    expect($html)->toContain('href="/boutique/page/2"');
    expect($html)->toContain('aria-current="page"');
});

it('brings the listing back into view when the theme asks', function (): void {
    $this->requesting('/boutique');

    $asked = Blade::render('<x-meilifacets::listing.pagination name="products" scroll />');
    $silent = Blade::render('<x-meilifacets::listing.pagination name="products" />');

    expect($asked)->toContain(Contract::ScrollAttribute->value);
    expect($silent)->not->toContain(Contract::ScrollAttribute->value);
});

it('links every page it shows and the steps around the current one', function (): void {
    $html = $this->rendered(new Pagination(2, 10, 40, 1000));

    expect($this->hrefsIn($html))->toBe(['/boutique?marque=globex', '/boutique?marque=globex', '/boutique/page/2?marque=globex', '/boutique/page/3?marque=globex', '/boutique/page/4?marque=globex', '/boutique/page/3?marque=globex']);
    expect($html)->not->toContain('<button');
});

it('marks the page being read and hides the slots left empty', function (): void {
    $html = $this->rendered(new Pagination(2, 10, 40, 1000));

    expect($html)->toMatch('#href="/boutique/page/2\?marque=globex"\s+aria-current="page"#');
    expect(preg_match_all('#<a class="meilifacetsPaginationPage"\s+hidden#', $html))->toBe(3);
});

it('escapes what the visitor wrote in the address', function (): void {
    $html = $this->rendered(new Pagination(1, 10, 40, 1000), 'marque="><script>');

    expect($html)->not->toContain('"><script>');
});
