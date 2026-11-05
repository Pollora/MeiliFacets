<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Search\ListingResults;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListingResultsTest extends TestCase
{
    #[Test]
    public function it_hands_back_each_card_with_the_id_of_its_post(): void
    {
        $results = $this->resultsOf([['ID' => 12, 'card' => ['title' => 'Soap']]]);

        $this->assertSame([['title' => 'Soap', 'id' => 12]], $results->cards());
    }

    /** A variation's card links to the product it belongs to. */
    #[Test]
    public function it_prefers_the_parent_id_over_the_document_id(): void
    {
        $results = $this->resultsOf([['ID' => 31, 'parent_id' => 30, 'card' => ['title' => 'Soap 50 ml']]]);

        $this->assertSame(30, $results->cards()[0]['id']);
    }

    #[Test]
    public function it_overrides_an_id_the_card_already_carries(): void
    {
        $results = $this->resultsOf([['ID' => 12, 'card' => ['id' => 99, 'title' => 'Soap']]]);

        $this->assertSame(12, $results->cards()[0]['id']);
    }

    #[Test]
    public function it_leaves_a_card_without_id_when_the_hit_has_none(): void
    {
        $results = $this->resultsOf([['card' => ['title' => 'Soap']]]);

        $this->assertSame([['title' => 'Soap']], $results->cards());
    }

    #[Test]
    public function it_leaves_out_a_hit_with_no_card_or_a_card_that_is_not_an_array(): void
    {
        $results = $this->resultsOf([
            ['ID' => 1],
            ['ID' => 2, 'card' => 'Soap'],
            ['ID' => 3, 'card' => null],
            ['ID' => 4, 'card' => ['title' => 'Candle']],
        ]);

        $this->assertSame([['title' => 'Candle', 'id' => 4]], $results->cards());
    }

    #[Test]
    public function it_hands_back_no_card_when_there_is_no_hit(): void
    {
        $this->assertSame([], $this->resultsOf([])->cards());
    }

    #[Test]
    public function it_hands_back_the_counts_of_a_taxonomy_and_none_for_an_unknown_one(): void
    {
        $results = new ListingResults([], 0, ['product_brand' => ['acme' => 3]]);

        $this->assertSame(['acme' => 3], $results->distribution('product_brand'));
        $this->assertSame([], $results->distribution('product_cat'));
    }

    /** `null` means nothing narrowed the listing; an empty array means the taxonomy had no value left. */
    #[Test]
    public function it_tells_an_unfiltered_taxonomy_with_no_value_from_an_unknown_one(): void
    {
        $results = new ListingResults([], 0, [], unfilteredDistributions: ['product_brand' => ['acme' => 3], 'product_cat' => []]);

        $this->assertSame(['acme' => 3], $results->unfilteredDistribution('product_brand'));
        $this->assertSame([], $results->unfilteredDistribution('product_cat'));
        $this->assertNull($results->unfilteredDistribution('product_tag'));
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     */
    private function resultsOf(array $hits): ListingResults
    {
        return new ListingResults($hits, count($hits), []);
    }
}
