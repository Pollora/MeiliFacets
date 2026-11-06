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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PaginationComponentTest extends TestCase
{
    use RequestsAnAddress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    protected function tearDown(): void
    {
        request()->server->remove('QUERY_STRING');
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_renders_its_links_from_the_listing_it_belongs_to(): void
    {
        $this->requesting('/boutique');

        $html = Blade::render('<x-meilifacets::listing.pagination name="products" />');

        $this->assertStringContainsString('href="/boutique/page/2"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    #[Test]
    public function it_brings_the_listing_back_into_view_when_the_theme_asks(): void
    {
        $this->requesting('/boutique');

        $asked = Blade::render('<x-meilifacets::listing.pagination name="products" scroll />');
        $silent = Blade::render('<x-meilifacets::listing.pagination name="products" />');

        $this->assertStringContainsString(Contract::ScrollAttribute->value, $asked);
        $this->assertStringNotContainsString(Contract::ScrollAttribute->value, $silent);
    }

    #[Test]
    public function it_links_every_page_it_shows_and_the_steps_around_the_current_one(): void
    {
        $html = $this->rendered(new Pagination(2, 10, 40, 1000));

        $this->assertSame(
            ['/boutique?marque=globex', '/boutique?marque=globex', '/boutique/page/2?marque=globex', '/boutique/page/3?marque=globex', '/boutique/page/4?marque=globex', '/boutique/page/3?marque=globex'],
            $this->hrefsIn($html),
        );
        $this->assertStringNotContainsString('<button', $html);
    }

    #[Test]
    public function it_marks_the_page_being_read_and_hides_the_slots_left_empty(): void
    {
        $html = $this->rendered(new Pagination(2, 10, 40, 1000));

        $this->assertMatchesRegularExpression('#href="/boutique/page/2\?marque=globex"\s+aria-current="page"#', $html);
        $this->assertSame(3, preg_match_all('#<a class="meilifacetsPaginationPage"\s+hidden#', $html));
    }

    #[Test]
    public function it_escapes_what_the_visitor_wrote_in_the_address(): void
    {
        $html = $this->rendered(new Pagination(1, 10, 40, 1000), 'marque="><script>');

        $this->assertStringNotContainsString('"><script>', $html);
    }

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
