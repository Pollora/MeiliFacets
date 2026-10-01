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

    /** A hook of equal priority runs before `setUp()`, which is what loads WordPress. */
    private const int AFTER_SET_UP = -1;

    #[Before(self::AFTER_SET_UP)]
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
