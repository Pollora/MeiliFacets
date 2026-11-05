<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\TermField;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Term;

final class WordPressTermHierarchyTest extends TestCase
{
    use HoldsProbeTerms;

    private const string ANCESTORS_FILTER = 'get_ancestors';

    private const int NO_SUCH_TERM = PHP_INT_MAX;

    private WP_Term $care;

    private WP_Term $face;

    private WP_Term $creams;

    private WP_Term $hair;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerProbeTaxonomy();
        $this->care = $this->probeTerm('Soins &amp; beauté', 'probe-care');
        $this->face = $this->probeTerm('Visage', 'probe-face', $this->care->term_id);
        $this->creams = $this->probeTerm('Crèmes', 'probe-creams', $this->face->term_id);
        $this->hair = $this->probeTerm('Cheveux', 'probe-hair', $this->care->term_id);
    }

    protected function tearDown(): void
    {
        $this->forgetProbeTaxonomy();

        parent::tearDown();
    }

    #[Test]
    public function it_describes_each_ancestor_nearest_first_with_the_fields_a_document_indexes(): void
    {
        $ancestors = new WordPressTermHierarchy()->ancestorsOf($this->creams->term_id, self::PROBE_TAXONOMY);

        $this->assertSame([$this->described($this->face), $this->described($this->care)], $ancestors);
    }

    #[Test]
    public function it_finds_no_ancestor_above_a_root_term(): void
    {
        $this->assertSame([], new WordPressTermHierarchy()->ancestorsOf($this->care->term_id, self::PROBE_TAXONOMY));
    }

    #[Test]
    public function it_leaves_out_an_ancestor_wordpress_no_longer_holds(): void
    {
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

        $this->assertSame([$this->described($this->care)], $ancestors);
    }

    #[Test]
    public function it_lists_the_direct_children_only_even_those_no_post_uses(): void
    {
        $children = new WordPressTermHierarchy()->childrenOf($this->care->term_id, self::PROBE_TAXONOMY);

        $this->assertEqualsCanonicalizing(['probe-face', 'probe-hair'], $children);
        $this->assertTrue(array_is_list($children));
    }

    #[Test]
    public function it_lists_the_top_level_terms_as_the_children_of_the_root(): void
    {
        $this->assertSame(['probe-care'], new WordPressTermHierarchy()->childrenOf(0, self::PROBE_TAXONOMY));
    }

    #[Test]
    public function it_lists_no_child_under_a_leaf(): void
    {
        $this->assertSame([], new WordPressTermHierarchy()->childrenOf($this->creams->term_id, self::PROBE_TAXONOMY));
    }

    #[Test]
    public function it_lists_no_child_in_a_taxonomy_wordpress_does_not_know(): void
    {
        $this->assertSame([], new WordPressTermHierarchy()->childrenOf(0, 'meilifacets_unregistered'));
    }

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
