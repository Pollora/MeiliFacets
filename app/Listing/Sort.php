<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class Sort
{
    /**
     * @param  list<string>  $expressions  Meilisearch sort expressions
     */
    public function __construct(
        public string $label,
        public array $expressions,
    ) {}
}
