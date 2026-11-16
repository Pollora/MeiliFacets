<?php

declare(strict_types=1);

use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Modules\MeiliFacets\Contracts\EngineUnavailable;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\ApplyShape;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Enums\RankingRule;
use Modules\MeiliFacets\Enums\ResetShape;
use Modules\MeiliFacets\Enums\Stylesheet;
use Modules\MeiliFacets\Search\FacetTruncated;
use Modules\MeiliFacets\Search\MeilisearchEngine;
use Modules\MeiliFacets\View\HiddenField;

it('falls back to submitting on an unknown apply setting', function (): void {
    expect(ApplyMode::fromSetting('immediate'))->toBe(ApplyMode::Immediate);
    expect(ApplyMode::fromSetting('whenever'))->toBe(ApplyMode::OnSubmit);
    expect(ApplyMode::OnSubmit->needsButton())->toBeTrue();
    expect(ApplyMode::Immediate->needsButton())->toBeFalse();
});

it('writes no shape for the default apply block and reset text', function (): void {
    expect(ApplyShape::Block->mark())->toBeNull();
    expect(ApplyShape::Pill->mark())->toBe('pill');
    expect(ApplyShape::Pill->showsCount())->toBeTrue();
    expect(ApplyShape::Block->showsCount())->toBeFalse();
    expect(ResetShape::Text->mark())->toBeNull();
    expect(ResetShape::Icon->mark())->toBe('icon');
});

it('prefixes each document key with the field that holds it', function (): void {
    expect(DocumentField::Price->path('min'))->toBe('price.min');
    expect(DocumentField::Labels->paths(['a', 'b']))->toBe(['labels.a', 'labels.b']);
});

it('lists every ranking rule in meilisearch order', function (): void {
    expect(RankingRule::relevanceFirst())->toBe(
        ['words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness']
    );
});

it('names the query parameter and hook of each price bound', function (): void {
    expect(PriceBound::Min->parameter())->toBe(QueryParameter::MinPrice);
    expect(PriceBound::Max->parameter())->toBe(QueryParameter::MaxPrice);
    expect(PriceBound::Min->hook())->toBe(Hook::PriceMin);
    expect(PriceBound::Max->hook())->toBe(Hook::PriceMax);
    expect(PriceBound::Min->label())->toBe('From');
    expect(PriceBound::Max->label())->toBe('To');
    expect(PriceBound::Min->handleLabel())->toBe('Lowest price');
    expect(PriceBound::Max->handleLabel())->toBe('Highest price');
});

it('reads each query parameter under the key the browser knows', function (): void {
    $keys = array_map(static fn (QueryParameter $parameter): string => $parameter->key(), QueryParameter::cases());

    expect($keys)->toBe(['sort', 'query', 'page', 'minPrice', 'maxPrice']);
});

it('lists the product taxonomies that are flags not words', function (): void {
    expect(ProductTaxonomy::technical())->toBe(
        ['product_visibility', 'product_type', 'product_shipping_class', 'pos_product_visibility']
    );
});

it('publishes each stylesheet under the module folder', function (): void {
    expect(Stylesheet::Listing->source())->toBe('modules/meilifacets/css/meilifacets.css');
    expect(Stylesheet::SiteSearch->source())->toBe('modules/meilifacets/css/site-search.css');
});

it('writes a hook as an attribute and as an attribute map', function (): void {
    expect((string) Hook::FacetValue->attribute())->toBe('data-meili="facet-value"');
    expect(Hook::Drawer->asAttributes())->toBe(['data-meili' => 'drawer']);
});

it('decodes a hidden field from a query pair', function (): void {
    $field = HiddenField::fromQueryPair('post_type=pro%20duct');

    expect($field->name)->toBe('post_type');
    expect($field->value)->toBe('pro duct');
    expect(HiddenField::fromQueryPair('flag')->value)->toBe('');
    expect(HiddenField::fromQueryPair('k=a=b')->value)->toBe('a=b');
});

it('names the taxonomy and the setting to raise when a facet is truncated', function (): void {
    $failure = FacetTruncated::at('product_brand', 100);

    expect($failure)->toBeInstanceOf(RuntimeException::class);
    expect($failure->getMessage())->toContain('"product_brand" returned 100 values');
    expect($failure->getMessage())->toContain('meilifacets.engine.max_facet_values');
});

it('tells each outage apart by what to check', function (): void {
    expect(EngineUnavailable::unreachable(new RuntimeException)->getMessage())->toContain('MEILI_HOST');
    expect(EngineUnavailable::incomplete(2, 1)->getMessage())->toContain('1 of 2 searches');
    expect(EngineUnavailable::unconfigured()->getMessage())->toContain('MEILI_SEARCH_KEY');
});

it('searches nothing when nothing is asked', function (): void {
    expect(new MeilisearchEngine(null, 'posts')->multiSearch([]))->toBe([]);
});

it('refuses a search without a client', function (): void {
    $this->expectException(EngineUnavailable::class);
    $this->expectExceptionMessage('No Meilisearch client');

    new MeilisearchEngine(null, 'posts')->multiSearch(['results' => []]);
});

it('reports a failing client as an unreachable engine', function (): void {
    $client = new class extends Client
    {
        public function __construct() {}

        public function multiSearch(array $queries = [], ?MultiSearchFederation $federation = null): array
        {
            throw new RuntimeException('connection refused');
        }
    };

    try {
        new MeilisearchEngine($client, 'posts')->multiSearch(['results' => []]);
        $this->fail('The outage was swallowed.');
    } catch (EngineUnavailable $failure) {
        expect($failure->getPrevious()?->getMessage())->toBe('connection refused');
    }
});
