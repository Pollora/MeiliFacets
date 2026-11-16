<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Tests\Feature\Concerns\HoldsProbeTerms;
use stdClass;
use Tests\TestCase;

trait WordPressTermLabelsHelpers
{
    private const string TERMS_FILTER = 'get_terms';

    private const string QUERY_ACTION = 'pre_get_terms';
}

uses(TestCase::class, HoldsProbeTerms::class, WordPressTermLabelsHelpers::class);

beforeEach(function (): void {
    $this->registerProbeTaxonomy();
    $this->probeTerm('Fruits & légumes', 'probe-produce');
    $this->probeTerm('Épices', 'probe-spices');
    $this->probeTerm('Thés', 'probe-teas');
});

afterEach(function (): void {
    $this->forgetProbeTaxonomy();
});

it('names each slug asked for and no other even one no post uses', function (): void {
    $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-spices', 'probe-teas']);

    $this->assertEqualsCanonicalizing(['probe-spices' => 'Épices', 'probe-teas' => 'Thés'], $labels);
});

/** WordPress stores `&` as `&amp;`: Blade would encode it a second time. */
it('names a term in plain text', function (): void {
    $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-produce']);

    expect($labels)->toBe(['probe-produce' => 'Fruits & légumes']);
});

it('leaves out a slug the taxonomy does not hold', function (): void {
    $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-teas', 'probe-gone']);

    expect($labels)->toBe(['probe-teas' => 'Thés']);
});

/** An empty `slug` would lift the restriction and name the whole taxonomy. */
it('names nothing and asks wordpress nothing for no slug', function (): void {
    $queries = 0;
    $count = static function () use (&$queries): void {
        $queries++;
    };
    add_action(self::QUERY_ACTION, $count);

    try {
        $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, []);
    } finally {
        remove_action(self::QUERY_ACTION, $count);
    }

    expect($labels)->toBe([]);
    expect($queries)->toBe(0);
});

it('names nothing in a taxonomy wordpress does not know', function (): void {
    expect(new WordPressTermLabels()->of('meilifacets_unregistered', ['probe-teas']))->toBe([]);
});

it('leaves out what a filter slips in that is no term', function (): void {
    $withAStranger = static fn (array $terms): array => [...$terms, new stdClass, 'probe-intruder'];
    add_filter(self::TERMS_FILTER, $withAStranger);

    try {
        $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-teas']);
    } finally {
        remove_filter(self::TERMS_FILTER, $withAStranger);
    }

    expect($labels)->toBe(['probe-teas' => 'Thés']);
});
