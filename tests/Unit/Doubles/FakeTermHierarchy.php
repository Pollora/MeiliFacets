<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\TermHierarchy;

final class FakeTermHierarchy implements TermHierarchy
{
    public int $lookups = 0;

    private const int ROOT = 0;

    /**
     * @param  array<string, array<int, array{slug: string, parent: int}>>  $tree
     */
    public function __construct(private readonly array $tree = []) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function ancestorsOf(int $termId, string $taxonomy): array
    {
        $this->lookups++;

        $terms = $this->tree[$taxonomy] ?? [];
        $ancestors = [];
        $parent = $terms[$termId]['parent'] ?? self::ROOT;

        while (isset($terms[$parent])) {
            ['slug' => $slug, 'parent' => $grandParent] = $terms[$parent];

            $ancestors[] = [
                'term_id' => $parent,
                'slug' => $slug,
                'taxonomy' => $taxonomy,
                'parent' => $grandParent,
            ];

            $parent = $grandParent;
        }

        return $ancestors;
    }
}
