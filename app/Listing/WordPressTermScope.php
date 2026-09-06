<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\TermScope;
use WP_Term;

final class WordPressTermScope implements TermScope
{
    private const int ROOT = 0;

    /** @var array<string, list<string>> */
    private array $children = [];

    public function __construct(private readonly TermHierarchy $hierarchy) {}

    /**
     * @return list<string>
     */
    public function childrenOf(string $taxonomy): array
    {
        // One query per taxonomy, however many facets and values read it.
        return $this->children[$taxonomy] ??= $this->hierarchy->childrenOf(
            $this->currentTermIn($taxonomy),
            $taxonomy
        );
    }

    private function currentTermIn(string $taxonomy): int
    {
        $term = get_queried_object();

        return $term instanceof WP_Term && $term->taxonomy === $taxonomy
            ? (int) $term->term_id
            : self::ROOT;
    }
}
