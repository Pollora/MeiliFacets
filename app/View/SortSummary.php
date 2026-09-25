<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** What the trigger of the sort reads: its name, then the order in force, as the catalogue words them. */
final readonly class SortSummary
{
    /** A private-use character: no translation holds one, so the pattern splits around it. */
    private const string CHOICE = "\u{E000}";

    public function __construct(
        public string $label,
        public string $lead,
        public string $choice,
        public string $trail,
    ) {}

    public static function of(string $choice): self
    {
        $label = __('Sort by');
        [$before, $trail] = explode(self::CHOICE, __('Sort by: :choice', ['choice' => self::CHOICE]), 2) + [1 => ''];

        return new self($label, str_starts_with($before, $label) ? substr($before, strlen($label)) : $before, $choice, $trail);
    }
}
