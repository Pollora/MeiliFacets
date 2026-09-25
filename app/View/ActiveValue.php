<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\ActiveValueKind;

/** One filter the listing holds, as a pill that takes it off: its words, what it is, and the parameter and value it removes. */
final readonly class ActiveValue
{
    public function __construct(
        public string $label,
        public string $parameter,
        public string $value,
        public string $action,
        public ActiveValueKind $kind,
    ) {}
}
