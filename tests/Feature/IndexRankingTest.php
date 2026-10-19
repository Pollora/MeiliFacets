<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Enums\RankingRule;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** MeiliScout 2 ranks by the chosen sort before relevance. */
final class IndexRankingTest extends TestCase
{
    #[Test]
    public function it_ranks_by_relevance_before_the_chosen_sort(): void
    {
        $rules = $this->app->make(FacetedPostIndexable::class)->getIndexSettings()[IndexSetting::RankingRules->value];

        $this->assertSame(RankingRule::Words->value, $rules[0]);
        $this->assertGreaterThan(
            array_search(RankingRule::AttributeRank->value, $rules, true),
            array_search(RankingRule::Sort->value, $rules, true),
        );
    }
}
