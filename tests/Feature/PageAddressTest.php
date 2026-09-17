<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Http\PageAddress;
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
        $this->requesting('/boutique/seite/2?marque=aeris', 'seite');

        $this->assertSame('/boutique', $this->page()->path());
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
