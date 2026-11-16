<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;

trait DependentDocumentsFilterSteps
{
    /** Far above any real post. */
    private const int GONE = 2_000_000_001;

    /** @var list<int> */
    private array $created = [];

    /**
     * @param  non-empty-list<int>  $itemIds
     */
    private function filterOf(array $itemIds): ?string
    {
        return $this->app->make(FacetedPostIndexable::class)->dependentDocumentsFilter($itemIds);
    }

    private function postOfType(string $postType): int
    {
        $post = ['post_type' => $postType, 'post_title' => 'Dependent documents filter, for the test', 'post_status' => 'draft'];
        $postId = wp_insert_post($post);

        return $this->created[] = $postId;
    }
}

uses(TestCase::class, KeepsTheIndexOut::class, DependentDocumentsFilterSteps::class);

afterEach(function (): void {
    foreach ($this->created as $postId) {
        wp_delete_post($postId, true);
    }
});

it('has no filter for a page', function (): void {
    expect($this->filterOf([$this->postOfType('page')]))->toBeNull();
});

it('filters only the products of a mixed batch', function (): void {
    $productId = $this->postOfType('product');

    expect($this->filterOf([$this->postOfType('page'), $productId]))->toBe("parent_id IN [{$productId}]");
});

it('has no filter for a variation', function (): void {
    expect($this->filterOf([$this->postOfType('product_variation')]))->toBeNull();
});

it('keeps a post already gone', function (): void {
    expect($this->filterOf([self::GONE]))->toBe('parent_id IN ['.self::GONE.']');
});
