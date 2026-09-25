<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** What the trigger of the sort reads: its name, then the order in force, as the catalogue words them. */
final readonly class SortSummary
{
    /** Where the value pattern puts the order in force. */
    private const string CHOICE = ':choice';

    public function __construct(
        public string $label,
        public string $lead,
        public string $choice,
        public string $trail,
    ) {}

    /** `$valuePattern` is what follows the label, the order in force written `:choice`. */
    public static function of(string $label, string $valuePattern, string $choice): self
    {
        [$lead, $trail] = explode(self::CHOICE, $valuePattern, 2) + [1 => ''];

        return new self($label, $lead, $choice, $trail);
    }
}
