<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\EngineUnavailable;
use Modules\MeiliFacets\Contracts\SearchEngine;

final class UnavailableSearchEngine implements SearchEngine
{
    public function multiSearch(array $queries): array
    {
        throw EngineUnavailable::unconfigured();
    }
}
