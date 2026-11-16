<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Tests\TestCase;

trait SortComponentHelpers
{
    private const string PROMOTIONS = 'on_sale';

    /** @var array{concrete: \Closure, shared: bool} */
    private array $engine;

    private function promotions(int $onSale): Element
    {
        $this->app->scoped(SearchEngine::class, static fn (): FakeSearchEngine => new FakeSearchEngine([
            'results' => ['facetDistribution' => ['price.onsale' => $onSale === 0 ? ['false' => 9] : ['true' => $onSale]]],
        ]));
        $this->app->forgetScopedInstances();

        $markup = Blade::render('<x-meilifacets::listing.sort name="products" />');
        $option = HTMLDocument::createFromString('<div>'.$markup.'</div>', LIBXML_NOERROR)
            ->querySelector('[data-value="'.self::PROMOTIONS.'"]');

        $this->assertInstanceOf(Element::class, $option);

        return $option;
    }

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->named(ProductListing::NAME);
    }
}

uses(TestCase::class, SortComponentHelpers::class);

beforeEach(function (): void {
    $this->engine = $this->app->getBindings()[SearchEngine::class];

    $this->app->forgetScopedInstances();

    if (! array_key_exists(self::PROMOTIONS, $this->listing()->sorts())) {
        $this->markTestSkipped('The host listing declares no price filter, so it offers no promotions to render.');
    }
});

/** The suite shares one application: a fake engine left bound would answer a later class. */
afterEach(function (): void {
    $this->app->bind(SearchEngine::class, $this->engine['concrete'], $this->engine['shared']);
    request()->replace([]);
    $this->app->forgetScopedInstances();
});

it('renders promotions hidden when none is left in the selection', function (): void {
    expect($this->promotions(onSale: 0)->hasAttribute('hidden'))->toBeTrue();
});

it('renders promotions when some are left', function (): void {
    expect($this->promotions(onSale: 3)->hasAttribute('hidden'))->toBeFalse();
});

it('never hides promotions while they are the sort in use', function (): void {
    request()->replace(['sort' => self::PROMOTIONS]);

    expect($this->promotions(onSale: 0)->hasAttribute('hidden'))->toBeFalse();
});

it('refuses a second sort on the page', function (): void {
    $this->expectExceptionMessageMatches('/The sort of listing "[^"]+" is rendered twice/');

    Blade::render('<x-meilifacets::listing.sort name="products" /><x-meilifacets::listing.sort name="products" />');
});
