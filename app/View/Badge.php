<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** A count that describes its control. Empty at zero: a hidden node still describes, and a « 0 » would be read out. */
final readonly class Badge
{
    private const int NOTHING = 0;

    public function __construct(
        public string $id,
        private int $count,
    ) {}

    public function holdsNothing(): bool
    {
        return $this->count === self::NOTHING;
    }

    public function text(): string
    {
        return $this->holdsNothing() ? '' : (string) $this->count;
    }
}
