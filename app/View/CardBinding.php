<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\View\ComponentAttributeBag;
use Modules\MeiliFacets\Enums\BindingAttribute;
use Modules\MeiliFacets\Enums\CardField;

/**
 * @phpstan-type Fields BackedEnum|string|list<BackedEnum|string>
 */
final readonly class CardBinding
{
    private const string FIELD_PATTERN = '/^[A-Za-z0-9_]+$/';

    private const string CLASS_LIST_PATTERN = '/\s+/';

    private const string CLASS_ATTRIBUTE = 'class';

    /**
     * @param  array<string, mixed>|null  $card  `null` for the template
     */
    private function __construct(private ?array $card) {}

    /**
     * @param  array<string, mixed>  $card
     */
    public static function of(array $card): self
    {
        return new self($card);
    }

    public static function template(): self
    {
        return new self(null);
    }

    public function text(BackedEnum|string $field): CardFieldElement
    {
        $text = $this->textOf([$field]);

        if ($this->leavesOut($text)) {
            return CardFieldElement::absent();
        }

        return CardFieldElement::of(new ComponentAttributeBag([BindingAttribute::Text->value => $this->fieldName($field)]), e($text));
    }

    /** WooCommerce formats the price with markup at indexing time: the one field written as HTML. */
    public function price(): CardFieldElement
    {
        $markup = $this->textOf([CardField::Price]);

        if ($this->leavesOut($markup)) {
            return CardFieldElement::absent();
        }

        return CardFieldElement::of(new ComponentAttributeBag, $markup);
    }

    public function onlyWith(BackedEnum|string $field): CardFieldElement
    {
        if ($this->isTemplate() || $this->isTrue($field)) {
            return $this->condition($this->fieldName($field));
        }

        return CardFieldElement::absent();
    }

    public function onlyWithout(BackedEnum|string $field): CardFieldElement
    {
        if ($this->isTemplate() || ! $this->isTrue($field)) {
            return $this->condition($this->negated($field));
        }

        return CardFieldElement::absent();
    }

    /**
     * An attribute takes the first of its fields that holds a value: `['alt' => [ImageAlt, Title]]`.
     *
     * @param  array<string, Fields>  $fields  attribute name to the field or fields written into it
     */
    public function attributes(array $fields): ComponentAttributeBag
    {
        $written = [];
        $pairs = [];

        foreach ($fields as $name => $field) {
            $names = is_array($field) ? $field : [$field];
            $attribute = CardFieldAttribute::named($name);
            $value = CardFieldAttribute::written($this->textOf($names));
            $pairs[] = $this->pair($name, $names);

            if ($attribute->accepts($value)) {
                $written[$name] = e($value);
            }
        }

        return new ComponentAttributeBag([...$written, ...$this->listMarker(BindingAttribute::Attribute, $pairs)]);
    }

    /**
     * @param  array<string, BackedEnum|string>  $toggles  classes, space-separated, to the field that switches them on
     */
    public function classes(array $toggles): ComponentAttributeBag
    {
        $switchedOn = [];
        $pairs = [];

        foreach ($toggles as $classes => $field) {
            foreach ($this->classNames($classes) as $class) {
                $pairs[] = $this->pair($class, [$field]);
                $switchedOn[$class] = $this->isTrue($field);
            }
        }

        return new ComponentAttributeBag([
            self::CLASS_ATTRIBUTE => e(Arr::toCssClasses($switchedOn)),
            ...$this->listMarker(BindingAttribute::ClassName, $pairs),
        ]);
    }

    public function classList(BackedEnum|string $field): ComponentAttributeBag
    {
        $classes = $this->classNames($this->textOf([$field]));

        return new ComponentAttributeBag([
            self::CLASS_ATTRIBUTE => e(implode(BindingAttribute::LIST_SEPARATOR, $classes)),
            BindingAttribute::ClassList->value => e($this->fieldName($field)),
        ]);
    }

    private function condition(string $condition): CardFieldElement
    {
        return CardFieldElement::of(new ComponentAttributeBag([BindingAttribute::Condition->value => e($condition)]));
    }

    private function isTrue(BackedEnum|string $field): bool
    {
        return $this->read($field)->isTrue();
    }

    private function negated(BackedEnum|string $field): string
    {
        return BindingAttribute::NEGATION.$this->fieldName($field);
    }

    private function leavesOut(string $content): bool
    {
        return ! $this->isTemplate() && $content === '';
    }

    private function isTemplate(): bool
    {
        return $this->card === null;
    }

    /**
     * @param  list<string>  $pairs
     * @return array<string, string>
     */
    private function listMarker(BindingAttribute $attribute, array $pairs): array
    {
        return [$attribute->value => e(implode(BindingAttribute::LIST_SEPARATOR, $pairs))];
    }

    /**
     * @param  list<BackedEnum|string>  $fields
     */
    private function textOf(array $fields): string
    {
        foreach ($fields as $field) {
            $text = $this->read($field)->text();

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function read(BackedEnum|string $field): CardFieldValue
    {
        return new CardFieldValue($this->card[$this->fieldName($field)] ?? null);
    }

    private function fieldName(BackedEnum|string $field): string
    {
        $name = $field instanceof BackedEnum ? (string) $field->value : $field;

        if (preg_match(self::FIELD_PATTERN, $name) !== 1) {
            throw BindingRefused::field($name);
        }

        return $name;
    }

    /**
     * @param  list<BackedEnum|string>  $fields
     */
    private function pair(string $target, array $fields): string
    {
        $names = array_map($this->fieldName(...), $fields);

        return $target.BindingAttribute::PAIR_SEPARATOR.implode(BindingAttribute::FALLBACK_SEPARATOR, $names);
    }

    /**
     * @return list<string>
     */
    private function classNames(string $classes): array
    {
        return preg_split(self::CLASS_LIST_PATTERN, $classes, flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
