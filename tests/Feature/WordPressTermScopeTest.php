<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\WordPressTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermHierarchy;
use Modules\MeiliFacets\Tests\Unit\Doubles\RecordingTermHierarchy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Post;
use WP_Query;
use WP_Term;

/** The queried object is set in memory on the global query, and put back after each test. */
final class WordPressTermScopeTest extends TestCase
{
    private const string CATEGORIES = 'product_cat';

    private const string BRANDS = 'product_brand';

    private const int ROOT = 0;

    private const int FACE_CARE = 31;

    private ?WP_Query $hostQuery = null;

    private RecordingTermHierarchy $hierarchy;

    protected function setUp(): void
    {
        parent::setUp();

        $hostQuery = $GLOBALS['wp_query'] ?? null;
        $this->hostQuery = $hostQuery instanceof WP_Query ? $hostQuery : null;
        $GLOBALS['wp_query'] = new WP_Query;

        $this->hierarchy = new RecordingTermHierarchy(
            new FakeTermHierarchy()
                ->withChildren(self::ROOT, self::CATEGORIES, ['care', 'home'])
                ->withChildren(self::FACE_CARE, self::CATEGORIES, ['creams', 'serums'])
                ->withChildren(self::ROOT, self::BRANDS, ['acme'])
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['wp_query'] = $this->hostQuery;

        parent::tearDown();
    }

    #[Test]
    public function it_scopes_a_taxonomy_to_the_children_of_the_term_archive_being_shown(): void
    {
        $this->showing($this->term(self::FACE_CARE, self::CATEGORIES));

        $this->assertSame(['creams', 'serums'], $this->scope()->childrenOfCurrentTerm(self::CATEGORIES));
    }

    #[Test]
    public function it_scopes_another_taxonomy_than_the_archive_to_its_top_level(): void
    {
        $this->showing($this->term(self::FACE_CARE, self::CATEGORIES));

        $this->assertSame(['acme'], $this->scope()->childrenOfCurrentTerm(self::BRANDS));
    }

    #[Test]
    public function it_scopes_to_the_top_level_on_a_page_that_is_no_term_archive(): void
    {
        $this->showing(new WP_Post((object) ['ID' => self::FACE_CARE, 'post_type' => 'page', 'post_name' => 'face-care']));

        $this->assertSame(['care', 'home'], $this->scope()->childrenOfCurrentTerm(self::CATEGORIES));
    }

    #[Test]
    public function it_scopes_to_the_top_level_when_nothing_is_queried(): void
    {
        $this->assertSame(['care', 'home'], $this->scope()->childrenOfCurrentTerm(self::CATEGORIES));
    }

    #[Test]
    public function it_asks_the_hierarchy_once_per_taxonomy(): void
    {
        $scope = $this->scope();

        $scope->childrenOfCurrentTerm(self::CATEGORIES);
        $scope->childrenOfCurrentTerm(self::CATEGORIES);
        $scope->childrenOfCurrentTerm(self::BRANDS);

        $this->assertSame(
            [self::CATEGORIES => 1, self::BRANDS => 1],
            array_count_values($this->hierarchy->childrenAsked)
        );
    }

    private function scope(): WordPressTermScope
    {
        return new WordPressTermScope($this->hierarchy);
    }

    private function showing(WP_Term|WP_Post $queried): void
    {
        $GLOBALS['wp_query']->queried_object = $queried;
    }

    private function term(int $termId, string $taxonomy): WP_Term
    {
        return new WP_Term((object) ['term_id' => $termId, 'taxonomy' => $taxonomy, 'slug' => 'face-care']);
    }
}
