<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\AnonymousVisitor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnonymousVisitorTest extends TestCase
{
    #[Test]
    public function it_only_reads_on_a_site_without_wordpress(): void
    {
        if (function_exists('wp_set_current_user')) {
            $this->markTestSkipped('WordPress is loaded: another suite ran first.');
        }

        $this->assertSame('card', new AnonymousVisitor()->during(static fn (): string => 'card'));
    }
}
