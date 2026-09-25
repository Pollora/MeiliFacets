<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** What the trigger of the sort reads: its name alone, then the sentence that holds the order in force. */
final readonly class SortSummary
{
    public function __construct(
        public string $label,
        public string $lead,
        public string $choice,
        public string $trail,
    ) {}
}
