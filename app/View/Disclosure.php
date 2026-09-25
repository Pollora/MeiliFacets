<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** What the trigger of a collapsible filter shows: its name, the panel it controls, and what it holds when it counts. */
final readonly class Disclosure
{
    public function __construct(
        public string $label,
        public string $panelId,
        public ?Badge $badge = null,
        public bool $labelRestated = false,
    ) {}

    /** The slot says the label again in a sentence: the label is shown alone, and never read out twice. */
    public static function restatedInItsSlot(string $label, string $panelId): self
    {
        return new self($label, $panelId, labelRestated: true);
    }
}
