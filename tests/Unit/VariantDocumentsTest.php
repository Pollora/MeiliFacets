<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\VariantDocuments;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VariantDocumentsTest extends TestCase
{
    private const array PRODUCT = [
        'ID' => 125,
        'post_title' => 'Lotion',
        'facets' => ['pa_volume' => ['15ml', '400ml'], 'product_brand' => ['odessa']],
        'price' => ['min' => 26, 'max' => 39, 'onsale' => true],
        'in_stock' => 1,
        'document_kind' => 'parent',
        'card' => [
            'title' => 'Lotion',
            'variants' => [
                ['facets' => ['pa_volume' => ['15ml']], 'price' => 26, 'fields' => [], 'in_stock' => true],
                ['facets' => ['pa_volume' => ['400ml']], 'price' => 39, 'fields' => [], 'in_stock' => false],
            ],
        ],
    ];

    #[Test]
    public function it_writes_one_document_per_variant_under_the_variant_terms_price_and_stock(): void
    {
        $documents = new VariantDocuments()->of(self::PRODUCT);

        $this->assertSame([
            ...array_diff_key(self::PRODUCT, ['document_kind' => true]),
            'ID' => '125-1',
            'parent_id' => 125,
            'post_type' => 'product_variation',
            'in_stock' => 0,
            'facets' => ['pa_volume' => ['400ml'], 'product_brand' => ['odessa']],
            'price' => ['min' => 39.0, 'max' => 39.0, 'onsale' => true],
        ], $documents[1]);
        $this->assertSame(['125-0', '125-1'], array_column($documents, 'ID'));
        $this->assertSame([], array_column($documents, 'document_kind'));
        $this->assertSame([1, 0], array_column($documents, 'in_stock'));
    }

    #[Test]
    public function it_writes_none_for_a_product_without_variants(): void
    {
        $product = [...self::PRODUCT, 'card' => ['title' => 'Lotion']];

        $this->assertSame([], new VariantDocuments()->of($product));
    }

    #[Test]
    public function it_writes_none_for_a_document_without_a_numeric_id(): void
    {
        $this->assertSame([], new VariantDocuments()->of([...self::PRODUCT, 'ID' => '125']));
    }

    #[Test]
    public function it_skips_what_is_not_a_variant(): void
    {
        $product = self::PRODUCT;
        $product['card']['variants'] = [['price' => 'free'], ...self::PRODUCT['card']['variants']];

        $this->assertSame(['125-0', '125-1'], array_column(new VariantDocuments()->of($product), 'ID'));
    }

    #[Test]
    public function it_finds_the_variant_documents_of_several_products(): void
    {
        $this->assertSame('parent_id IN [125, 126]', new VariantDocuments()->filterOf([125, 126]));
    }

    /** Nothing but a post ID reaches the filter that deletes documents with the admin key. */
    #[Test]
    public function it_writes_no_text_into_the_filter_of_a_product_s_variants(): void
    {
        $this->assertSame('parent_id IN [125, 0]', new VariantDocuments()->filterOf(['125', '] OR ID > [0']));
    }
}
