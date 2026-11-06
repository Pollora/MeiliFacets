<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\UrlParameters;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlParametersTest extends TestCase
{
    #[Test]
    public function it_owns_its_mapped_reserved_and_prefixed_names_and_nothing_else(): void
    {
        $parameters = new UrlParameters(['product_brand' => 'marque'], ['sort' => 'tri']);

        $this->assertSame(
            [true, true, true, false, false],
            array_map($parameters->owns(...), ['marque', 'tri', 'f_product_cat', 'sort', 'utm_source']),
        );
    }

    #[Test]
    public function it_gives_a_mapped_taxonomy_the_configured_name(): void
    {
        $parameters = new UrlParameters(['product_brand' => 'marque']);

        $this->assertSame('marque', $parameters->for('product_brand'));
    }

    #[Test]
    public function it_prefixes_a_taxonomy_left_unmapped(): void
    {
        $parameters = new UrlParameters([]);

        $this->assertSame('f_product_cat', $parameters->for('product_cat'));
    }

    #[Test]
    public function it_never_hands_back_a_bare_taxonomy_name(): void
    {
        $parameters = new UrlParameters(['product_brand' => 'marque']);

        foreach (['product_cat', 'product_tag', 'collection', 'highlight'] as $reserved) {
            $this->assertNotSame($reserved, $parameters->for($reserved));
        }
    }
}
