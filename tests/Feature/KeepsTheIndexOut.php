<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * A product saved by a test would reach the real index, settings included (R-171).
 */
trait KeepsTheIndexOut
{
    private const string SKIP_INDEXING = 'meiliscout/skip_indexing';

    #[Before]
    protected function keepTheIndexOut(): void
    {
        add_filter(self::SKIP_INDEXING, '__return_true');
    }

    #[After]
    protected function letTheIndexIn(): void
    {
        remove_filter(self::SKIP_INDEXING, '__return_true');
    }
}
