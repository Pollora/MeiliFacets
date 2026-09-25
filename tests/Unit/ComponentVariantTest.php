<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\ApplyShape;
use Modules\MeiliFacets\Enums\ResetShape;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

final class ComponentVariantTest extends TestCase
{
    #[Test]
    public function it_reads_a_variant_written_plainly_or_bound(): void
    {
        $this->assertSame(ApplyShape::Block, ApplyShape::fromAttribute('block'));
        $this->assertSame(ResetShape::Icon, ResetShape::fromAttribute(ResetShape::Icon));
    }

    #[Test]
    public function it_refuses_a_variant_the_enum_does_not_declare(): void
    {
        $this->expectException(ValueError::class);

        ApplyShape::fromAttribute('round');
    }
}
