<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\View\ListingDescription;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListingDescriptionTest extends TestCase
{
    use RequestsAnAddress;
    use SwitchesTheSiteLocale;

    private const string PROMOTIONS = 'on_sale';

    /** The suite shares one application: a query left behind would reach a later class. */
    protected function tearDown(): void
    {
        request()->query->replace([]);
        request()->server->remove('QUERY_STRING');
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_hands_the_client_the_state_the_server_read(): void
    {
        $parameters = $this->app->make(UrlParameters::class);
        $taxonomy = $this->listing()->facets()[0]->taxonomy;

        $state = $this->describedWith([
            $parameters->for($taxonomy) => 'globex,acme',
            $parameters->reserved(QueryParameter::Page) => '3',
            $parameters->reserved(QueryParameter::MinPrice) => '12.5',
        ])['state'];

        $this->assertSame([$taxonomy => ['acme', 'globex']], (array) $state['facets']);
        $this->assertSame(3, $state['page']);
        $this->assertSame(['min' => 12.5, 'max' => null], $state['price']);
        $this->assertSame('', $state['query']);
        $this->assertNull($state['sort']);
    }

    /** Missing, the whole site answers 500: the resolved listing copies the contract by hand. */
    #[Test]
    public function it_always_hands_the_client_a_base_query(): void
    {
        $this->assertSame('', $this->describedWith([])['baseQuery']);
    }

    #[Test]
    public function it_hands_over_the_path_of_the_first_page(): void
    {
        $this->requesting('/boutique/page/2?marque=aeris');

        $this->assertSame('/boutique', $this->describedWith([])['pagePath']);
    }

    #[Test]
    public function it_hands_over_the_counts_of_the_values_the_page_renders(): void
    {
        $description = $this->describedWith([]);
        $facet = $this->listing()->facets()[0];
        $expected = [];

        foreach ($this->listing()->valuesOf($facet) as $value) {
            $expected[$value->slug] = $value->count;
        }

        $this->assertSame($expected, (array) $description['facets'][0]['counts']);
        $this->assertMatchesRegularExpression('/^[a-z]{2,3}(-[A-Za-z0-9]+)*$/', $description['locale']);
    }

    #[Test]
    public function it_hands_over_its_labels_in_the_language_wordpress_translates_the_page_in(): void
    {
        $description = $this->underLocales('fr', 'en_US', fn (): array => $this->describedWith([]));

        $this->assertSame('en-US', $description['locale']);
        $this->assertSame(':count result|:count results', $description['countPattern']);
    }

    #[Test]
    public function it_hands_over_no_selection_as_an_empty_object(): void
    {
        $this->assertSame('{}', json_encode($this->describedWith([])['state']['facets']));
    }

    #[Test]
    public function it_hands_over_what_wordpress_read_less_the_listings_own_parameters(): void
    {
        global $wp;

        $parameters = $this->app->make(UrlParameters::class);
        $owned = [
            $parameters->reserved(QueryParameter::MinPrice),
            $parameters->for($this->listing()->facets()[0]->taxonomy),
        ];

        $publicQueryVars = $wp->public_query_vars;
        array_walk($owned, $wp->add_query_var(...));

        try {
            $pageQuery = $this->describedWith([
                's' => '', 'post_type' => 'product', 'add-to-cart' => '12', 'paged' => '2',
                $owned[0] => '10', $owned[1] => 'acme',
            ])['pageQuery'];
        } finally {
            $wp->public_query_vars = $publicQueryVars;
        }

        $this->assertSame('s=&post_type=product', $pageQuery);
    }

    #[Test]
    public function it_tells_the_client_which_field_promotions_filter_on(): void
    {
        if (! array_key_exists(self::PROMOTIONS, $this->listing()->sorts())) {
            $this->markTestSkipped('The host listing declares no price filter, so it offers no promotions.');
        }

        $description = $this->app->make(ListingDescription::class)->of($this->listing());

        $this->assertSame(['field' => 'price.onsale', 'value' => 'true'], ((array) $description['sortFilters'])[self::PROMOTIONS]);
        $this->assertSame([], $description['sorts'][self::PROMOTIONS]);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    /** The same shape is written twice, once per language: a key added on one side only is a field nobody reads. */
    #[Test]
    public function it_publishes_exactly_the_fields_the_client_declares(): void
    {
        $published = array_keys($this->describedWith([]));
        $declared = $this->fieldsTheClientDeclares();

        sort($published);
        sort($declared);

        $this->assertSame($declared, $published);
    }

    /**
     * @return list<string>
     */
    private function fieldsTheClientDeclares(): array
    {
        $source = (string) file_get_contents(__DIR__.'/../../resources/assets/ts/shared/description.ts');

        preg_match('/export interface ListingDescription \{(.*?)\n\}/s', $source, $block);
        preg_match_all('/^ {4}(\w+)\??:/m', $block[1] ?? '', $fields);

        return $fields[1];
    }

    private function describedWith(array $query): array
    {
        $this->app->forgetScopedInstances();
        request()->query->replace($query);
        request()->server->set('QUERY_STRING', http_build_query($query));

        return $this->app->make(ListingDescription::class)->of($this->listing());
    }

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->sole();
    }
}
