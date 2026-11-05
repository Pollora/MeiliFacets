<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Support\NamedRegistry;
use stdClass;

/**
 * @extends NamedRegistry<stdClass>
 */
final class FakeRegistry extends NamedRegistry
{
    public int $lookups = 0;

    /**
     * @param  array<string, stdClass>  $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    protected function kind(): string
    {
        return 'shelf';
    }

    protected function beforeLookup(): void
    {
        $this->lookups++;
    }
}
