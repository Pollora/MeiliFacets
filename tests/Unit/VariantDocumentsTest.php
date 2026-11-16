<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\WooCommerce\VariantDocuments;

const VARIANT_DOCUMENTS_PRODUCT = [
    'ID' => 125,
    'post_title' => 'Lotion',
    'facets' => ['pa_volume' => ['15ml', '400ml'], 'product_brand' => ['odessa']],
    'price' => ['min' => 26, 'max' => 39, 'onsale' => true],
    'in_stock' => 1,
    'document_kind' => 'parent',
    'card' => [
        'title' => 'Lotion',
        'variants' => [
            ['facets' => ['pa_volume' => ['15ml']], 'price' => 26, 'fields' => [], 'in_stock' => true, 'on_sale' => true],
            ['facets' => ['pa_volume' => ['400ml']], 'price' => 39, 'fields' => [], 'in_stock' => false],
        ],
    ],
];

it('writes one document per variant under the variant terms price and stock', function (): void {
    $documents = new VariantDocuments()->of(VARIANT_DOCUMENTS_PRODUCT);

    expect($documents[1])->toBe([
        ...array_diff_key(VARIANT_DOCUMENTS_PRODUCT, ['document_kind' => true]),
        'ID' => '125-1',
        'parent_id' => 125,
        'post_type' => 'product_variation',
        'in_stock' => 0,
        'facets' => ['pa_volume' => ['400ml'], 'product_brand' => ['odessa']],
        'price' => ['min' => 39.0, 'max' => 39.0, 'onsale' => false],
    ]);
    expect(array_column($documents, 'ID'))->toBe(['125-0', '125-1']);
    expect(array_column($documents, 'document_kind'))->toBe([]);
    expect(array_column($documents, 'in_stock'))->toBe([1, 0]);
});

// The product is on sale as soon as one variation is: each variant answers for itself.
it('marks on sale only the variants that are', function (): void {
    $documents = new VariantDocuments()->of(VARIANT_DOCUMENTS_PRODUCT);

    expect(array_map(static fn (array $document): bool => $document['price']['onsale'], $documents))->toBe([true, false]);
});

it('writes none for a product without variants', function (): void {
    $product = [...VARIANT_DOCUMENTS_PRODUCT, 'card' => ['title' => 'Lotion']];

    expect(new VariantDocuments()->of($product))->toBe([]);
});

it('writes none for a document without a numeric id', function (): void {
    expect(new VariantDocuments()->of([...VARIANT_DOCUMENTS_PRODUCT, 'ID' => '125']))->toBe([]);
});

it('skips what is not a variant', function (): void {
    $product = VARIANT_DOCUMENTS_PRODUCT;
    $product['card']['variants'] = [['price' => 'free'], ...VARIANT_DOCUMENTS_PRODUCT['card']['variants']];

    expect(array_column(new VariantDocuments()->of($product), 'ID'))->toBe(['125-0', '125-1']);
});

it('finds the variant documents of several products', function (): void {
    expect(new VariantDocuments()->filterOf([125, 126]))->toBe('parent_id IN [125, 126]');
});

// Nothing but a post ID reaches the filter that deletes documents with the admin key.
it('writes no text into the filter of a product\'s variants', function (): void {
    expect(new VariantDocuments()->filterOf(['125', '] OR ID > [0']))->toBe('parent_id IN [125, 0]');
});
