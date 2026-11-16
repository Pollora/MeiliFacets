<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Enums\RankingRule;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Tests\TestCase;

// MeiliScout 2 ranks by the chosen sort before relevance.
uses(TestCase::class);

it('ranks by relevance before the chosen sort', function (): void {
    $rules = $this->app->make(FacetedPostIndexable::class)->getIndexSettings()[IndexSetting::RankingRules->value];

    expect($rules[0])->toBe(RankingRule::Words->value);
    expect(array_search(RankingRule::Sort->value, $rules, true))->toBeGreaterThan(array_search(RankingRule::AttributeRank->value, $rules, true));
});
