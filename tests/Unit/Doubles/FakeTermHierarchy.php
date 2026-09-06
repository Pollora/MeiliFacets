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
    /** @var array<string, list<string>> */
    private array $children = [];

    /**
     * @param  list<string>  $slugs
     */
    public function withChildren(int $termId, string $taxonomy, array $slugs): self
    {
        $this->children[$taxonomy.':'.$termId] = $slugs;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function childrenOf(int $termId, string $taxonomy): array
    {
        return $this->children[$taxonomy.':'.$termId] ?? [];
    }

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
