<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use RuntimeException;
use Throwable;

final class SearchFailed extends RuntimeException
{
    public static function unreachable(Throwable $previous): self
    {
        return new self('Meilisearch did not answer. Check MEILI_HOST and that the engine is running.', 0, $previous);
    }

    public static function unconfigured(): self
    {
        return new self('No Meilisearch client. Check MEILI_HOST and MEILI_SEARCH_KEY.');
    }
}
