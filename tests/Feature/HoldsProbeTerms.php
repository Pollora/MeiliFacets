<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use RuntimeException;
use WP_Term;

/**
 * A hierarchical taxonomy of the test's own, kept out of the index: call `registerProbeTaxonomy()`
 * in `setUp()` and `forgetProbeTaxonomy()` in `tearDown()`, which deletes every term inserted.
 */
trait HoldsProbeTerms
{
    private const string PROBE_TAXONOMY = 'meilifacets_probe';

    private const string SKIP_INDEXING = 'meiliscout/skip_indexing';

    /** @var list<int> */
    private array $probeTermIds = [];

    private function registerProbeTaxonomy(): void
    {
        // Not KeepsTheIndexOut: it guards from after `setUp()`, and the probe terms are inserted during it.
        add_filter(self::SKIP_INDEXING, '__return_true');
        register_taxonomy(self::PROBE_TAXONOMY, [], ['hierarchical' => true, 'public' => false]);
    }

    private function forgetProbeTaxonomy(): void
    {
        foreach (array_reverse($this->probeTermIds) as $termId) {
            wp_delete_term($termId, self::PROBE_TAXONOMY);
        }

        $this->probeTermIds = [];
        unregister_taxonomy(self::PROBE_TAXONOMY);
        remove_filter(self::SKIP_INDEXING, '__return_true');
    }

    private function probeTerm(string $name, string $slug, int $parent = 0): WP_Term
    {
        $inserted = wp_insert_term($name, self::PROBE_TAXONOMY, ['slug' => $slug, 'parent' => $parent]);

        if (! is_array($inserted)) {
            throw new RuntimeException("The probe term \"{$slug}\" could not be inserted.");
        }

        $termId = (int) $inserted['term_id'];
        $this->probeTermIds[] = $termId;

        return $this->readBack($termId);
    }

    private function readBack(int $termId): WP_Term
    {
        $term = get_term($termId, self::PROBE_TAXONOMY);

        if (! $term instanceof WP_Term) {
            throw new RuntimeException("The probe term {$termId} could not be read back.");
        }

        return $term;
    }
}
