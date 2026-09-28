<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Pollora\MeiliScout\Config\Settings;

final readonly class IndexedPostTypes
{
    private const string OPTION = 'indexed_post_types';

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_values((array) Settings::get(self::OPTION, []));
    }
}
