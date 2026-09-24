<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** What the trigger of a collapsible filter shows: its name, the panel it controls, and what it holds. */
final readonly class Disclosure
{
    public function __construct(
        public string $label,
        public string $panelId,
        public string $selectedCountId,
        public int $selectedCount,
    ) {}

    public function holdsNothing(): bool
    {
        return $this->selectedCount === 0;
    }

    /** Empty at zero: the trigger is described by the badge, and a hidden node still describes. */
    public function badge(): string
    {
        return $this->holdsNothing() ? '' : (string) $this->selectedCount;
    }
}
