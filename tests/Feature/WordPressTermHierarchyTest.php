<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\TermField;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Tests\Feature\Concerns\HoldsProbeTerms;
use Tests\TestCase;
use WP_Term;

trait WordPressTermHierarchySupport
{
    private const string ANCESTORS_FILTER = 'get_ancestors';

    private const int NO_SUCH_TERM = PHP_INT_MAX;

    private WP_Term $care;

    private WP_Term $face;

    private WP_Term $creams;

    private WP_Term $hair;

    /**
     * @return array<string, mixed>
     */
    private function described(WP_Term $term): array
    {
        return [
            TermField::TermId->value => $term->term_id,
            TermField::Name->value => $term->name,
            TermField::Slug->value => $term->slug,
            TermField::Taxonomy->value => self::PROBE_TAXONOMY,
            TermField::TermTaxonomyId->value => $term->term_taxonomy_id,
            TermField::Parent->value => $term->parent,
        ];
    }
}

uses(TestCase::class, HoldsProbeTerms::class, WordPressTermHierarchySupport::class);

beforeEach(function (): void {
    $this->registerProbeTaxonomy();
    $this->care = $this->probeTerm('Soins &amp; beauté', 'probe-care');
    $this->face = $this->probeTerm('Visage', 'probe-face', $this->care->term_id);
    $this->creams = $this->probeTerm('Crèmes', 'probe-creams', $this->face->term_id);
    $this->hair = $this->probeTerm('Cheveux', 'probe-hair', $this->care->term_id);
});

afterEach(function (): void {
    $this->forgetProbeTaxonomy();
});

it('describes each ancestor nearest first with the fields a document indexes', function (): void {
    $ancestors = new WordPressTermHierarchy()->ancestorsOf($this->creams->term_id, self::PROBE_TAXONOMY);

    expect($ancestors)->toBe([$this->described($this->face), $this->described($this->care)]);
});

it('finds no ancestor above a root term', function (): void {
    expect(new WordPressTermHierarchy()->ancestorsOf($this->care->term_id, self::PROBE_TAXONOMY))->toBe([]);
});

it('leaves out an ancestor wordpress no longer holds', function (): void {
    $withAVanishedAncestor = static function (array $ancestors): array {
        array_unshift($ancestors, self::NO_SUCH_TERM);

        return $ancestors;
    };
    add_filter(self::ANCESTORS_FILTER, $withAVanishedAncestor);

    try {
        $ancestors = new WordPressTermHierarchy()->ancestorsOf($this->face->term_id, self::PROBE_TAXONOMY);
    } finally {
        remove_filter(self::ANCESTORS_FILTER, $withAVanishedAncestor);
    }

    expect($ancestors)->toBe([$this->described($this->care)]);
});

it('lists the direct children only even those no post uses', function (): void {
    $children = new WordPressTermHierarchy()->childrenOf($this->care->term_id, self::PROBE_TAXONOMY);

    $this->assertEqualsCanonicalizing(['probe-face', 'probe-hair'], $children);
    expect(array_is_list($children))->toBeTrue();
});

it('lists the top level terms as the children of the root', function (): void {
    expect(new WordPressTermHierarchy()->childrenOf(0, self::PROBE_TAXONOMY))->toBe(['probe-care']);
});

it('lists no child under a leaf', function (): void {
    expect(new WordPressTermHierarchy()->childrenOf($this->creams->term_id, self::PROBE_TAXONOMY))->toBe([]);
});

it('lists no child in a taxonomy wordpress does not know', function (): void {
    expect(new WordPressTermHierarchy()->childrenOf(0, 'meilifacets_unregistered'))->toBe([]);
});
