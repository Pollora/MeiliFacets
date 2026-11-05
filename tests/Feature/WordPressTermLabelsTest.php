<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\WordPressTermLabels;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

final class WordPressTermLabelsTest extends TestCase
{
    use HoldsProbeTerms;

    private const string TERMS_FILTER = 'get_terms';

    private const string QUERY_ACTION = 'pre_get_terms';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerProbeTaxonomy();
        $this->probeTerm('Fruits & légumes', 'probe-produce');
        $this->probeTerm('Épices', 'probe-spices');
        $this->probeTerm('Thés', 'probe-teas');
    }

    protected function tearDown(): void
    {
        $this->forgetProbeTaxonomy();

        parent::tearDown();
    }

    #[Test]
    public function it_names_each_slug_asked_for_and_no_other_even_one_no_post_uses(): void
    {
        $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-spices', 'probe-teas']);

        $this->assertEqualsCanonicalizing(['probe-spices' => 'Épices', 'probe-teas' => 'Thés'], $labels);
    }

    /** WordPress stores `&` as `&amp;`: Blade would encode it a second time. */
    #[Test]
    public function it_names_a_term_in_plain_text(): void
    {
        $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-produce']);

        $this->assertSame(['probe-produce' => 'Fruits & légumes'], $labels);
    }

    #[Test]
    public function it_leaves_out_a_slug_the_taxonomy_does_not_hold(): void
    {
        $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-teas', 'probe-gone']);

        $this->assertSame(['probe-teas' => 'Thés'], $labels);
    }

    /** An empty `slug` would lift the restriction and name the whole taxonomy. */
    #[Test]
    public function it_names_nothing_and_asks_wordpress_nothing_for_no_slug(): void
    {
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

        $this->assertSame([], $labels);
        $this->assertSame(0, $queries);
    }

    #[Test]
    public function it_names_nothing_in_a_taxonomy_wordpress_does_not_know(): void
    {
        $this->assertSame([], new WordPressTermLabels()->of('meilifacets_unregistered', ['probe-teas']));
    }

    #[Test]
    public function it_leaves_out_what_a_filter_slips_in_that_is_no_term(): void
    {
        $withAStranger = static fn (array $terms): array => [...$terms, new stdClass, 'probe-intruder'];
        add_filter(self::TERMS_FILTER, $withAStranger);

        try {
            $labels = new WordPressTermLabels()->of(self::PROBE_TAXONOMY, ['probe-teas']);
        } finally {
            remove_filter(self::TERMS_FILTER, $withAStranger);
        }

        $this->assertSame(['probe-teas' => 'Thés'], $labels);
    }
}
