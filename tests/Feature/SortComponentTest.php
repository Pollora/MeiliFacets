<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SortComponentTest extends TestCase
{
    private const string PROMOTIONS = 'on_sale';

    /** @var array{concrete: \Closure, shared: bool} */
    private array $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->app->getBindings()[SearchEngine::class];

        $this->app->forgetScopedInstances();

        if (! array_key_exists(self::PROMOTIONS, $this->listing()->sorts())) {
            $this->markTestSkipped('The host listing declares no price filter, so it offers no promotions to render.');
        }
    }

    /** The suite shares one application: a fake engine left bound would answer a later class. */
    protected function tearDown(): void
    {
        $this->app->bind(SearchEngine::class, $this->engine['concrete'], $this->engine['shared']);
        request()->replace([]);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_renders_promotions_hidden_when_none_is_left_in_the_selection(): void
    {
        $this->assertTrue($this->promotions(onSale: 0)->hasAttribute('hidden'));
    }

    #[Test]
    public function it_renders_promotions_when_some_are_left(): void
    {
        $this->assertFalse($this->promotions(onSale: 3)->hasAttribute('hidden'));
    }

    #[Test]
    public function it_never_hides_promotions_while_they_are_the_sort_in_use(): void
    {
        request()->replace(['sort' => self::PROMOTIONS]);

        $this->assertFalse($this->promotions(onSale: 0)->hasAttribute('hidden'));
    }

    #[Test]
    public function it_refuses_a_second_sort_on_the_page(): void
    {
        $this->expectExceptionMessageMatches('/The sort of listing "[^"]+" is rendered twice/');

        Blade::render('<x-meilifacets::sort /><x-meilifacets::sort />');
    }

    private function promotions(int $onSale): Element
    {
        $this->app->scoped(SearchEngine::class, static fn (): FakeSearchEngine => new FakeSearchEngine([
            'results' => ['facetDistribution' => ['price.onsale' => $onSale === 0 ? ['false' => 9] : ['true' => $onSale]]],
        ]));
        $this->app->forgetScopedInstances();

        $markup = Blade::render('<x-meilifacets::sort />');
        $option = HTMLDocument::createFromString('<div>'.$markup.'</div>', LIBXML_NOERROR)
            ->querySelector('[data-value="'.self::PROMOTIONS.'"]');

        $this->assertInstanceOf(Element::class, $option);

        return $option;
    }

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->sole();
    }
}
