<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\TermAncestry;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermHierarchy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TermAncestryTest extends TestCase
{
    private const array CATEGORY_TREE = [
        'category' => [
            60 => ['slug' => 'coats', 'parent' => 55],
            55 => ['slug' => 'outerwear', 'parent' => 50],
            50 => ['slug' => 'clothing', 'parent' => 0],
        ],
    ];

    private FakeTermHierarchy $hierarchy;

    private TermAncestry $ancestry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = new FakeTermHierarchy(self::CATEGORY_TREE);
        $this->ancestry = new TermAncestry($this->hierarchy);
    }

    #[Test]
    public function it_leaves_a_root_term_untouched(): void
    {
        $terms = [$this->term(50, 'clothing', 'category', 0)];

        $this->assertSame($terms, $this->ancestry->expand($terms));
        $this->assertSame(0, $this->hierarchy->lookups);
    }

    #[Test]
    public function it_appends_the_whole_chain_of_a_nested_term(): void
    {
        $expanded = $this->ancestry->expand([$this->term(60, 'coats', 'category', 55)]);

        $this->assertSame(['coats', 'outerwear', 'clothing'], array_column($expanded, 'slug'));
    }

    #[Test]
    public function it_keeps_the_other_terms_of_the_document(): void
    {
        $expanded = $this->ancestry->expand([
            $this->term(26, 'acme', 'brand', 0),
            $this->term(55, 'outerwear', 'category', 50),
        ]);

        $this->assertSame(['acme', 'outerwear', 'clothing'], array_column($expanded, 'slug'));
    }

    #[Test]
    public function it_carries_a_shared_ancestor_once(): void
    {
        $expanded = $this->ancestry->expand([
            $this->term(55, 'outerwear', 'category', 50),
            $this->term(60, 'coats', 'category', 55),
        ]);

        $this->assertSame(['outerwear', 'coats', 'clothing'], array_column($expanded, 'slug'));
    }

    #[Test]
    public function it_resolves_a_shared_shelf_once_across_a_batch(): void
    {
        $document = [$this->term(55, 'outerwear', 'category', 50)];

        $this->ancestry->expand($document);
        $this->ancestry->expand($document);

        $this->assertSame(1, $this->hierarchy->lookups);
    }

    #[Test]
    public function it_skips_a_term_without_a_usable_taxonomy(): void
    {
        $terms = [['term_id' => 55, 'slug' => 'outerwear', 'parent' => 50]];

        $this->assertSame($terms, $this->ancestry->expand($terms));
        $this->assertSame(0, $this->hierarchy->lookups);
    }

    #[Test]
    public function it_treats_an_unreadable_parent_as_a_root(): void
    {
        $terms = [['term_id' => 55, 'slug' => 'outerwear', 'taxonomy' => 'category']];

        $this->assertSame($terms, $this->ancestry->expand($terms));
        $this->assertSame(0, $this->hierarchy->lookups);
    }

    #[Test]
    public function it_reads_an_identifier_written_as_a_string(): void
    {
        $term = ['term_id' => '60', 'slug' => 'coats', 'taxonomy' => 'category', 'parent' => '55'];

        $this->assertSame(
            ['coats', 'outerwear', 'clothing'],
            array_column($this->ancestry->expand([$term]), 'slug')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function term(int $id, string $slug, string $taxonomy, int $parent): array
    {
        return [
            'term_id' => $id,
            'slug' => $slug,
            'taxonomy' => $taxonomy,
            'parent' => $parent,
        ];
    }
}
