<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\WooCommerce\AnonymousVisitor;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The WordPress doubles live in the shared process: only a process of its own is still without WordPress. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AnonymousVisitorTest extends TestCase
{
    #[Test]
    public function it_only_reads_on_a_site_without_wordpress(): void
    {
        $this->assertFalse(function_exists('wp_set_current_user'));
        $this->assertSame('card', new AnonymousVisitor()->during(static fn (): string => 'card'));
    }
}
