<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DependentDocumentsFilterTest extends TestCase
{
    use KeepsTheIndexOut;

    /** Far above any real post. */
    private const int GONE = 2_000_000_001;

    /** @var list<int> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $postId) {
            wp_delete_post($postId, true);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_has_no_filter_for_a_page(): void
    {
        $this->assertNull($this->filterOf([$this->postOfType('page')]));
    }

    #[Test]
    public function it_filters_only_the_products_of_a_mixed_batch(): void
    {
        $productId = $this->postOfType('product');

        $this->assertSame("parent_id IN [{$productId}]", $this->filterOf([$this->postOfType('page'), $productId]));
    }

    #[Test]
    public function it_has_no_filter_for_a_variation(): void
    {
        $this->assertNull($this->filterOf([$this->postOfType('product_variation')]));
    }

    #[Test]
    public function it_keeps_a_post_already_gone(): void
    {
        $this->assertSame('parent_id IN ['.self::GONE.']', $this->filterOf([self::GONE]));
    }

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
