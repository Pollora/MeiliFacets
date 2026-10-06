<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Search\VisibleProducts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VisibleProductsTest extends TestCase
{
    private const string OF_PRODUCTS = 'post_type = "product"';

    private const string OF_PRODUCTS_OR_VARIATIONS = '(post_type = "product" OR post_type = "product_variation")';

    private const string WITHOUT_PARENTS = 'NOT document_kind = "parent"';

    #[Test]
    public function it_reads_the_products_on_their_post_type_alone(): void
    {
        $filter = implode(' AND ', VisibleProducts::inCatalogue());

        $this->assertStringContainsString(self::OF_PRODUCTS, $filter);
        $this->assertStringNotContainsString('document_kind', $filter);
        $this->assertStringNotContainsString('product_variation', $filter);
    }

    #[Test]
    public function it_reads_the_variants_instead_of_their_parents(): void
    {
        $clauses = VisibleProducts::onVariants(VisibleProducts::inCatalogue());

        $this->assertContains(self::OF_PRODUCTS_OR_VARIATIONS, $clauses);
        $this->assertNotContains(self::OF_PRODUCTS, $clauses);
        $this->assertContains(self::WITHOUT_PARENTS, $clauses);
    }

    #[Test]
    public function it_admits_the_variations_into_a_project_filter(): void
    {
        $clauses = VisibleProducts::onVariants([self::OF_PRODUCTS, 'facets.product_brand = "acme"']);

        $this->assertSame([self::OF_PRODUCTS_OR_VARIATIONS, 'facets.product_brand = "acme"', self::WITHOUT_PARENTS], $clauses);
    }

    #[Test]
    public function it_keeps_a_post_type_clause_written_otherwise(): void
    {
        $clauses = VisibleProducts::onVariants(["post_type = 'product'"]);

        $this->assertSame(["post_type = 'product'", self::WITHOUT_PARENTS], $clauses);
    }
}
