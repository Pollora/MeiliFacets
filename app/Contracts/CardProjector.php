<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use WP_Post;

interface CardProjector
{
    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array;
}
