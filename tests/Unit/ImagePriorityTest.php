<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\ImagePriority;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImagePriorityTest extends TestCase
{
    #[Test]
    public function it_loads_the_cards_above_the_fold_eagerly(): void
    {
        $this->assertSame(ImagePriority::Eager, ImagePriority::forRank(0, 4));
        $this->assertSame(ImagePriority::Eager, ImagePriority::forRank(3, 4));
        $this->assertSame(ImagePriority::Lazy, ImagePriority::forRank(4, 4));
    }

    #[Test]
    public function it_never_hints_a_priority_it_does_not_mean(): void
    {
        $this->assertSame('eager', ImagePriority::Eager->loading());
        $this->assertSame('high', ImagePriority::Eager->fetchPriority());
        $this->assertSame('lazy', ImagePriority::Lazy->loading());
        $this->assertSame('auto', ImagePriority::Lazy->fetchPriority());
    }
}
