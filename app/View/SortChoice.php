<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

final readonly class SortChoice
{
    public function __construct(
        public string $value,
        public string $label,
        public string $id,
        public bool $selected,
        public bool $hidden = false,
    ) {}

    public function hide(): self
    {
        return new self($this->value, $this->label, $this->id, $this->selected, hidden: true);
    }
}
