<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use BackedEnum;
use InvalidArgumentException;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Contracts\ValueOrder;
use Modules\MeiliFacets\Contracts\ValuePresentation;
use Modules\MeiliFacets\Enums\DefaultTerm;
use Modules\MeiliFacets\Enums\DisplayOrder;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\SelectionMode;

readonly class Facet implements Placeable
{
    public const int DEFAULT_VISIBLE = 10;

    public const int DEFAULT_CAP = 30;

    /** What a template designates this facet by, so no view carries a taxonomy name. */
    public string $name;

    public ValuePresentation $presentation;

    public function __construct(
        public string $taxonomy,
        public string $label,
        public SelectionMode $selection = SelectionMode::Multiple,
        public DisplayOrder|ValueOrder $order = DisplayOrder::Count,
        public int $visible = self::DEFAULT_VISIBLE,
        public int $cap = self::DEFAULT_CAP,
        public DefaultTerm $defaultTerm = DefaultTerm::Hidden,
        string|BackedEnum $name = '',
        ValuePresentation $presentation = Presentation::Control,
    ) {
        $this->name = match (true) {
            $name instanceof BackedEnum => (string) $name->value,
            $name !== '' => $name,
            default => $taxonomy,
        };
        $this->presentation = $this->presentedAs($presentation);
    }

    /**
     * @throws InvalidArgumentException when the facet holds one value at a time and the presentation cannot
     */
    public function presentedAs(ValuePresentation $presentation): ValuePresentation
    {
        if ($this->canBePresentedAs($presentation)) {
            return $presentation;
        }

        throw new InvalidArgumentException(sprintf(
            'Facet "%s" holds one value at a time, so its values cannot be presented as "%s": '
            .'they would be radios, which cannot be unchecked. Present it as "%s", or declare it with SelectionMode::%s.',
            $this->name,
            $presentation->slug(),
            Presentation::Control->slug(),
            SelectionMode::Multiple->name,
        ));
    }

    private function canBePresentedAs(ValuePresentation $presentation): bool
    {
        return $this->selection->allowsSeveralValues() || $presentation->allowsSingleSelection();
    }

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

    /** A flat facet of the taxonomy a path pins offers that one value and nothing else: it has stopped narrowing. */
    public function narrowsUnder(?string $pinned): bool
    {
        return $pinned !== $this->taxonomy;
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
