<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Contracts\ValueOrder;
use Modules\MeiliFacets\Enums\DefaultTerm;
use Modules\MeiliFacets\Enums\DisplayOrder;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\SelectionMode;

readonly class Facet
{
    public const int DEFAULT_VISIBLE = 10;

    public const int DEFAULT_CAP = 30;

    public function __construct(
        public string $taxonomy,
        public string $label,
        public SelectionMode $selection = SelectionMode::Multiple,
        public DisplayOrder|ValueOrder $order = DisplayOrder::Count,
        public int $visible = self::DEFAULT_VISIBLE,
        public int $cap = self::DEFAULT_CAP,
        public DefaultTerm $defaultTerm = DefaultTerm::Hidden,
    ) {}

    /**
     * Which of the values the engine returned this facet may show. A facet shows
     * them all unless it says otherwise.
     *
     * @param  array<string, int>  $distribution
     * @return array<string, int>
     */
    public function within(array $distribution, TermScope $scope): array
    {
        return $distribution;
    }

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
