<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnumsAndErrorsTest extends TestCase
{
    #[Test]
    public function it_falls_back_to_submitting_on_an_unknown_apply_setting(): void
    {
        $this->assertSame(ApplyMode::Immediate, ApplyMode::fromSetting('immediate'));
        $this->assertSame(ApplyMode::OnSubmit, ApplyMode::fromSetting('whenever'));
        $this->assertTrue(ApplyMode::OnSubmit->needsButton());
        $this->assertFalse(ApplyMode::Immediate->needsButton());
    }

    #[Test]
    public function it_writes_no_shape_for_the_default_apply_block_and_reset_text(): void
    {
        $this->assertNull(ApplyShape::Block->mark());
        $this->assertSame('pill', ApplyShape::Pill->mark());
        $this->assertTrue(ApplyShape::Pill->showsCount());
        $this->assertFalse(ApplyShape::Block->showsCount());
        $this->assertNull(ResetShape::Text->mark());
        $this->assertSame('icon', ResetShape::Icon->mark());
    }

    #[Test]
    public function it_prefixes_each_document_key_with_the_field_that_holds_it(): void
    {
        $this->assertSame('price.min', DocumentField::Price->path('min'));
        $this->assertSame(['labels.a', 'labels.b'], DocumentField::Labels->paths(['a', 'b']));
    }

    #[Test]
    public function it_lists_every_ranking_rule_in_meilisearch_order(): void
    {
        $this->assertSame(
            ['words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness'],
            RankingRule::relevanceFirst()
        );
    }

    #[Test]
    public function it_names_the_query_parameter_and_hook_of_each_price_bound(): void
    {
        $this->assertSame(QueryParameter::MinPrice, PriceBound::Min->parameter());
        $this->assertSame(QueryParameter::MaxPrice, PriceBound::Max->parameter());
        $this->assertSame(Hook::PriceMin, PriceBound::Min->hook());
        $this->assertSame(Hook::PriceMax, PriceBound::Max->hook());
        $this->assertSame('From', PriceBound::Min->label());
        $this->assertSame('To', PriceBound::Max->label());
        $this->assertSame('Lowest price', PriceBound::Min->handleLabel());
        $this->assertSame('Highest price', PriceBound::Max->handleLabel());
    }

    #[Test]
    public function it_reads_each_query_parameter_under_the_key_the_browser_knows(): void
    {
        $keys = array_map(static fn (QueryParameter $parameter): string => $parameter->key(), QueryParameter::cases());

        $this->assertSame(['sort', 'query', 'page', 'minPrice', 'maxPrice'], $keys);
    }

    #[Test]
    public function it_lists_the_product_taxonomies_that_are_flags_not_words(): void
    {
        $this->assertSame(
            ['product_visibility', 'product_type', 'product_shipping_class', 'pos_product_visibility'],
            ProductTaxonomy::technical()
        );
    }

    #[Test]
    public function it_publishes_each_stylesheet_under_the_module_folder(): void
    {
        $this->assertSame('modules/meilifacets/css/meilifacets.css', Stylesheet::Listing->source());
        $this->assertSame('modules/meilifacets/css/site-search.css', Stylesheet::SiteSearch->source());
    }

    #[Test]
    public function it_writes_a_hook_as_an_attribute_and_as_an_attribute_map(): void
    {
        $this->assertSame('data-meili="facet-value"', (string) Hook::FacetValue->attribute());
        $this->assertSame(['data-meili' => 'drawer'], Hook::Drawer->asAttributes());
    }

    #[Test]
    public function it_decodes_a_hidden_field_from_a_query_pair(): void
    {
        $field = HiddenField::fromQueryPair('post_type=pro%20duct');

        $this->assertSame('post_type', $field->name);
        $this->assertSame('pro duct', $field->value);
        $this->assertSame('', HiddenField::fromQueryPair('flag')->value);
        $this->assertSame('a=b', HiddenField::fromQueryPair('k=a=b')->value);
    }

    #[Test]
    public function it_names_the_taxonomy_and_the_setting_to_raise_when_a_facet_is_truncated(): void
    {
        $failure = FacetTruncated::at('product_brand', 100);

        $this->assertInstanceOf(RuntimeException::class, $failure);
        $this->assertStringContainsString('"product_brand" returned 100 values', $failure->getMessage());
        $this->assertStringContainsString('meilifacets.engine.max_facet_values', $failure->getMessage());
    }

    #[Test]
    public function it_tells_each_outage_apart_by_what_to_check(): void
    {
        $this->assertStringContainsString('MEILI_HOST', EngineUnavailable::unreachable(new RuntimeException)->getMessage());
        $this->assertStringContainsString('1 of 2 searches', EngineUnavailable::incomplete(2, 1)->getMessage());
        $this->assertStringContainsString('MEILI_SEARCH_KEY', EngineUnavailable::unconfigured()->getMessage());
    }

    #[Test]
    public function it_searches_nothing_when_nothing_is_asked(): void
    {
        $this->assertSame([], new MeilisearchEngine(null, 'posts')->multiSearch([]));
    }

    #[Test]
    public function it_refuses_a_search_without_a_client(): void
    {
        $this->expectException(EngineUnavailable::class);
        $this->expectExceptionMessage('No Meilisearch client');

        new MeilisearchEngine(null, 'posts')->multiSearch(['results' => []]);
    }

    #[Test]
    public function it_reports_a_failing_client_as_an_unreachable_engine(): void
    {
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
            $this->assertSame('connection refused', $failure->getPrevious()?->getMessage());
        }
    }
}
