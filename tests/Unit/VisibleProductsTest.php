<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Search\VisibleProducts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VisibleProductsTest extends TestCase
{
    private const string WITHOUT_VARIANTS = 'NOT document_kind = "variant"';

    private const string WITHOUT_PARENTS = 'NOT document_kind = "parent"';

    #[Test]
    public function it_reads_the_variants_instead_of_their_parents(): void
    {
        $clauses = VisibleProducts::onVariants(VisibleProducts::inCatalogue());

        $this->assertNotContains(self::WITHOUT_VARIANTS, $clauses);
        $this->assertContains(self::WITHOUT_PARENTS, $clauses);
    }

    /** A project's own product filter, written without the module's clause, still never lists a product twice. */
    #[Test]
    public function it_leaves_the_parents_out_of_a_filter_written_without_the_module_clause(): void
    {
        $clauses = VisibleProducts::onVariants(['post_type = "product"']);

        $this->assertSame(['post_type = "product"', self::WITHOUT_PARENTS], $clauses);
    }
}
