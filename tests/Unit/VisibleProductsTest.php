<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\VisibleProducts;

const VISIBLE_PRODUCTS_OF_PRODUCTS = 'post_type = "product"';

const VISIBLE_PRODUCTS_OF_PRODUCTS_OR_VARIATIONS = '(post_type = "product" OR post_type = "product_variation")';

const VISIBLE_PRODUCTS_WITHOUT_PARENTS = 'NOT document_kind = "parent"';

it('reads the products on their post type alone', function (): void {
    $filter = implode(' AND ', VisibleProducts::inCatalogue());

    expect($filter)->toContain(VISIBLE_PRODUCTS_OF_PRODUCTS);
    expect($filter)->not->toContain('document_kind');
    expect($filter)->not->toContain('product_variation');
});

it('reads the variants instead of their parents', function (): void {
    $clauses = VisibleProducts::onVariants(VisibleProducts::inCatalogue());

    expect($clauses)->toContain(VISIBLE_PRODUCTS_OF_PRODUCTS_OR_VARIATIONS);
    expect($clauses)->not->toContain(VISIBLE_PRODUCTS_OF_PRODUCTS);
    expect($clauses)->toContain(VISIBLE_PRODUCTS_WITHOUT_PARENTS);
});

it('admits the variations into a project filter', function (): void {
    $clauses = VisibleProducts::onVariants([VISIBLE_PRODUCTS_OF_PRODUCTS, 'facets.product_brand = "acme"']);

    expect($clauses)->toBe([VISIBLE_PRODUCTS_OF_PRODUCTS_OR_VARIATIONS, 'facets.product_brand = "acme"', VISIBLE_PRODUCTS_WITHOUT_PARENTS]);
});

it('keeps a post type clause written otherwise', function (): void {
    $clauses = VisibleProducts::onVariants(["post_type = 'product'"]);

    expect($clauses)->toBe(["post_type = 'product'", VISIBLE_PRODUCTS_WITHOUT_PARENTS]);
});
