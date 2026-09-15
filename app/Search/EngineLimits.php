<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

final readonly class EngineLimits
{
    /** Meilisearch's own default for `pagination.maxTotalHits`, on every index. */
    public const int DEFAULT_REACHABLE_HITS = 1000;

    /**
     * Ours, not the engine's: it caps `facetDistribution` at `ENGINE_MAX_FACET_VALUES`
     * and truncates by global count, with nothing said.
     */
    public const int DEFAULT_MAX_FACET_VALUES = 1000;

    /** What the engine applies until the module writes its own, and the value it replaces. */
    public const int ENGINE_MAX_FACET_VALUES = 100;

    public function __construct(
        public int $reachableHits,
        public int $maxFacetValues = self::DEFAULT_MAX_FACET_VALUES,
    ) {}

    /**
     * A distribution that stops on a round ceiling is one the engine may have cut. The
     * engine's own default counts too: between a release and its reindexing, the index
     * still caps at 100 while the module already asks for more.
     */
    public function looksTruncated(int $values): bool
    {
        return $values >= $this->maxFacetValues || $values === self::ENGINE_MAX_FACET_VALUES;
    }
}
