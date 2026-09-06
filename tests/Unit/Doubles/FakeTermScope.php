<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\TermScope;

final readonly class FakeTermScope implements TermScope
{
    /**
     * @param  array<string, list<string>>  $children
     */
    public function __construct(private array $children = []) {}

    /**
     * @return list<string>
     */
    public function childrenOf(string $taxonomy): array
    {
        return $this->children[$taxonomy] ?? [];
    }
}
