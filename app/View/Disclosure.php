<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\LabelReading;

/** What the trigger of a collapsible filter shows: its name, the panel it controls, and what it holds when it counts. */
final readonly class Disclosure
{
    public function __construct(
        public string $label,
        public string $panelId,
        public ?Badge $badge = null,
        public LabelReading $labelReading = LabelReading::Aloud,
    ) {}

    /** The slot says the label again in a sentence: the label is shown alone, and never read out twice. */
    public static function withSilentLabel(string $label, string $panelId): self
    {
        return new self($label, $panelId, labelReading: LabelReading::Silent);
    }

    public function readsLabelAloud(): bool
    {
        return $this->labelReading === LabelReading::Aloud;
    }
}
