<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\TermHierarchy;

final class RecordingTermHierarchy implements TermHierarchy
{
    /** @var list<string> taxonomy of each `childrenOf()` call, in order */
    public array $childrenAsked = [];

    public function __construct(private readonly TermHierarchy $hierarchy) {}

    public function ancestorsOf(int $termId, string $taxonomy): array
    {
        return $this->hierarchy->ancestorsOf($termId, $taxonomy);
    }

    public function childrenOf(int $termId, string $taxonomy): array
    {
        $this->childrenAsked[] = $taxonomy;

        return $this->hierarchy->childrenOf($termId, $taxonomy);
    }
}
