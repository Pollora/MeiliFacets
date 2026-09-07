<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

final readonly class EngineLimits
{
    /** Meilisearch's own default for `pagination.maxTotalHits`, on every index. */
    public const int DEFAULT_REACHABLE_HITS = 1000;

    public function __construct(public int $reachableHits) {}
}
