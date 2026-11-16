<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\WordPressTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermHierarchy;
use Modules\MeiliFacets\Tests\Unit\Doubles\RecordingTermHierarchy;
use Tests\TestCase;
use WP_Post;
use WP_Query;
use WP_Term;

trait WordPressTermScopeHelpers
{
    private const string CATEGORIES = 'product_cat';

    private const string BRANDS = 'product_brand';

    private const int ROOT = 0;

    private const int FACE_CARE = 31;

    private ?WP_Query $hostQuery = null;

    private RecordingTermHierarchy $hierarchy;

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

/** The queried object is set in memory on the global query, and put back after each test. */
uses(TestCase::class, WordPressTermScopeHelpers::class);

beforeEach(function (): void {
    $hostQuery = $GLOBALS['wp_query'] ?? null;
    $this->hostQuery = $hostQuery instanceof WP_Query ? $hostQuery : null;
    $GLOBALS['wp_query'] = new WP_Query;

    $this->hierarchy = new RecordingTermHierarchy(
        new FakeTermHierarchy()
            ->withChildren(self::ROOT, self::CATEGORIES, ['care', 'home'])
            ->withChildren(self::FACE_CARE, self::CATEGORIES, ['creams', 'serums'])
            ->withChildren(self::ROOT, self::BRANDS, ['acme'])
    );
});

afterEach(function (): void {
    $GLOBALS['wp_query'] = $this->hostQuery;
});

it('scopes a taxonomy to the children of the term archive being shown', function (): void {
    $this->showing($this->term(self::FACE_CARE, self::CATEGORIES));

    expect($this->scope()->childrenOfCurrentTerm(self::CATEGORIES))->toBe(['creams', 'serums']);
});

it('scopes another taxonomy than the archive to its top level', function (): void {
    $this->showing($this->term(self::FACE_CARE, self::CATEGORIES));

    expect($this->scope()->childrenOfCurrentTerm(self::BRANDS))->toBe(['acme']);
});

it('scopes to the top level on a page that is no term archive', function (): void {
    $this->showing(new WP_Post((object) ['ID' => self::FACE_CARE, 'post_type' => 'page', 'post_name' => 'face-care']));

    expect($this->scope()->childrenOfCurrentTerm(self::CATEGORIES))->toBe(['care', 'home']);
});

it('scopes to the top level when nothing is queried', function (): void {
    expect($this->scope()->childrenOfCurrentTerm(self::CATEGORIES))->toBe(['care', 'home']);
});

it('asks the hierarchy once per taxonomy', function (): void {
    $scope = $this->scope();

    $scope->childrenOfCurrentTerm(self::CATEGORIES);
    $scope->childrenOfCurrentTerm(self::CATEGORIES);
    $scope->childrenOfCurrentTerm(self::BRANDS);

    expect(array_count_values($this->hierarchy->childrenAsked))->toBe([self::CATEGORIES => 1, self::BRANDS => 1]);
});
