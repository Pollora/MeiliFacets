<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\ComponentAttributeBag;
use Modules\MeiliFacets\Enums\BindingAttribute;

/**
 * @phpstan-type Attributes ComponentAttributeBag|array<string, string>
 */
final readonly class CardFieldElement implements Htmlable
{
    private function __construct(public ComponentAttributeBag $attributes, private ?string $html) {}

    public static function of(ComponentAttributeBag $attributes, string $html = ''): self
    {
        return new self($attributes, $html);
    }

    public static function absent(): self
    {
        return new self(new ComponentAttributeBag, null);
    }

    public function isPresent(): bool
    {
        return $this->html !== null;
    }

    /**
     * @param  Attributes  ...$others
     *
     * @throws BindingRefused
     */
    public function with(ComponentAttributeBag|array ...$others): self
    {
        $attributes = $this->attributes;

        foreach ($others as $other) {
            $this->refuseASecondClassList($attributes, $other);

            $attributes = $other instanceof ComponentAttributeBag
                ? $attributes->merge($other->getAttributes(), escape: false)
                : $attributes->merge($other);
        }

        return new self($attributes, $this->html);
    }

    public function containing(string $text): self
    {
        return $this->isPresent() ? new self($this->attributes, e($text)) : $this;
    }

    public function toHtml(): string
    {
        return $this->html ?? '';
    }

    /**
     * `merge()` keeps the first value of any attribute but `class` and `style`.
     *
     * @param  Attributes  $other
     *
     * @throws BindingRefused
     */
    private function refuseASecondClassList(ComponentAttributeBag $attributes, ComponentAttributeBag|array $other): void
    {
        $marker = BindingAttribute::ClassList->value;
        $added = $other instanceof ComponentAttributeBag ? $other : new ComponentAttributeBag($other);

        if ($added->has($marker) && $attributes->has($marker)) {
            throw BindingRefused::secondClassList();
        }
    }
}
