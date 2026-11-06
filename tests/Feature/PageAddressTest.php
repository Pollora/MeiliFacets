<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Tests\Feature\Concerns\RequestsAnAddress;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PageAddressTest extends TestCase
{
    use RequestsAnAddress;

    /** The suite shares one application: a query left behind would reach a later class. */
    protected function tearDown(): void
    {
        request()->query->replace([]);
        request()->server->remove('QUERY_STRING');

        parent::tearDown();
    }

    #[Test]
    public function it_writes_the_first_page_under_whatever_name_wordpress_paginates(): void
    {
        $this->requesting('/boutique/seite/2?marque=globex', 'seite');

        $this->assertSame('/boutique', $this->page()->path());
    }

    #[Test]
    public function it_writes_where_the_number_of_a_later_page_goes(): void
    {
        $this->requesting('/boutique/page/3?marque=globex');
        $this->assertSame('/boutique/page/:page', $this->page()->pathPattern());

        $this->requesting('/boutique?marque=globex', 'seite');
        $this->assertSame('/boutique/seite/:page', $this->page()->pathPattern());
    }

    #[Test]
    public function it_links_a_page_in_the_path_the_listing_state_in_the_query(): void
    {
        $this->assertSame('/boutique/page/2?marque=globex&s=creme', $this->linkFrom('/boutique/page/3', 'marque=globex&s=creme', 2));
        $this->assertSame('/boutique?marque=globex', $this->linkFrom('/boutique/page/3', 'marque=globex', 1));
    }

    /** Repeated on every page, `add-to-cart` would fill the basket of whoever follows the links. */
    #[Test]
    public function it_links_without_what_neither_the_listing_nor_wordpress_reads(): void
    {
        $this->assertSame('/boutique/page/2?f_product_cat=visage', $this->linkFrom('/boutique', 'utm_source=news&f_product_cat=visage&add-to-cart=12', 2));
    }

    #[Test]
    public function it_links_without_a_page_number_the_path_already_carries(): void
    {
        $this->assertSame('/boutique/page/2?marque=globex', $this->linkFrom('/boutique', 'pg=5&paged=4&marque=globex', 2));
    }

    #[Test]
    public function it_writes_a_path_a_browser_cannot_read_as_a_host(): void
    {
        $this->assertSame('/boutique', $this->pathOf('//boutique/page/2'));
        $this->assertSame('/', $this->pathOf('//?s=creme'));
    }

    #[Test]
    public function it_keeps_only_what_wordpress_reads(): void
    {
        $this->assertSame('s=creme&post_type=product', $this->keptFrom('s=creme&add-to-cart=12&post_type=product&utm_source=news'));
    }

    #[Test]
    public function it_drops_the_page_wordpress_would_read_instead_of_the_listing(): void
    {
        $this->assertSame('s=creme', $this->keptFrom('paged=2&s=creme'));
    }

    #[Test]
    public function it_drops_what_the_listing_writes_itself(): void
    {
        $this->assertSame('post_type=product', $this->keptFrom('s=creme&post_type=product', ['s']));
    }

    #[Test]
    public function it_keeps_each_pair_as_php_received_it_not_as_laravel_left_it(): void
    {
        request()->server->set('QUERY_STRING', 's=&post_type=%20product%20');
        request()->query->replace(['s' => null, 'post_type' => 'product']);

        $this->assertSame('s=&post_type=%20product%20', $this->page()->queryWithout([]));
    }

    #[Test]
    public function it_keeps_a_search_written_without_a_value_and_every_repeat(): void
    {
        $this->assertSame('s&s=a&s=b', $this->keptFrom('s&s=a&utm_source=x&s=b'));
    }

    #[Test]
    public function it_reads_a_name_as_php_hands_it_to_wordpress(): void
    {
        $this->assertSame('post_type%5B%5D=product&post.type=page', $this->keptFrom('post_type%5B%5D=product&post.type=page&%FF=1'));
    }

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
