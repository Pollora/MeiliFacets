<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** One filter the listing holds, as a pill that takes it off: its words, and the name and value it removes. */
final readonly class ActiveValue
{
    public function __construct(
        public string $label,
        public string $parameter,
        public string $value,
        public string $action,
    ) {}
}
