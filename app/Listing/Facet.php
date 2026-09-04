<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\SelectionMode;

final readonly class Facet
{
    public const int DEFAULT_VISIBLE = 10;

    public const int DEFAULT_CAP = 30;

    public function __construct(
        public string $taxonomy,
        public string $label,
        public SelectionMode $selection = SelectionMode::Multiple,
        public int $visible = self::DEFAULT_VISIBLE,
        public int $cap = self::DEFAULT_CAP,
        public bool $highCardinality = false,
    ) {}

    public function field(): string
    {
        return DocumentField::Facets->path($this->taxonomy);
    }

    /**
     * Counting a facet without the constraint it carries only makes sense when
     * several of its values can be held at once.
     */
    public function needsDisjunctiveCount(): bool
    {
        return $this->selection->allowsSeveralValues();
    }
}
