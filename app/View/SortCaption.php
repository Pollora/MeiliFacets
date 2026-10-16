<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** What the trigger of the sort reads: its name alone, then the sentence that holds the order in force. */
final readonly class SortCaption
{
    public function __construct(
        public string $label,
        public string $lead,
        public ?string $choice,
        public string $trail,
    ) {}

    public static function withoutChoice(string $label): self
    {
        return new self($label, $label, null, '');
    }

    public function holdsChoice(): bool
    {
        return $this->choice !== null;
    }
}
