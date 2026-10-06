<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Dom\Element;
use Dom\HTMLDocument;
use Generator;
use Illuminate\View\ComponentAttributeBag;
use Modules\MeiliFacets\Enums\BindingAttribute;
use Modules\MeiliFacets\View\BindingRefused;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardFieldAttribute;
use Modules\MeiliFacets\View\CardFieldElement;
use Modules\MeiliFacets\View\CardFieldValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The server half of `tests/card-binding-cases.json`; `tests/ts/card-binding.test.ts`
 * applies the same cases in the browser, from the same template.
 *
 * @phpstan-type Binding array{
 *     text?: string,
 *     attributes?: array<string, string|list<string>>,
 *     classes?: array{always: string, toggles: array<string, string>},
 *     classList?: string,
 *     onlyWith?: string,
 *     onlyWithout?: string
 * }
 * @phpstan-type Expected array{
 *     text: string,
 *     elements: int,
 *     attributes: array<string, string|null>,
 *     classes: list<string>,
 *     present: bool
 * }
 */
final class CardBindingTest extends TestCase
{
    private const string CASES = __DIR__.'/../card-binding-cases.json';

    /**
     * @return Generator<string, array{Binding, string}>
     */
    public static function templates(): Generator
    {
        foreach (self::cases()['elements'] as $case) {
            yield $case['case'] => [$case['binding'], $case['template']];
        }
    }

    /**
     * @param  Binding  $binding
     */
    #[DataProvider('templates')]
    #[Test]
    public function the_template_is_the_card_rendered_empty(array $binding, string $template): void
    {
        $expected = $this->parsed($template);
        $rendered = $this->parsed($this->markup(CardBinding::template(), $binding));

        $this->assertInstanceOf(Element::class, $expected);
        $this->assertInstanceOf(Element::class, $rendered);
        $this->assertSame($this->described($expected), $this->described($rendered));
    }

    /**
     * @return Generator<string, array{Binding, array<string, mixed>, Expected}>
     */
    public static function elements(): Generator
    {
        foreach (self::cases()['elements'] as $case) {
            yield $case['case'] => [$case['binding'], $case['card'], $case['expected']];
        }
    }

    /**
     * @param  Binding  $binding
     * @param  array<string, mixed>  $card
     * @param  Expected  $expected
     */
    #[DataProvider('elements')]
    #[Test]
    public function it_renders_what_the_client_writes(array $binding, array $card, array $expected): void
    {
        $element = $this->parsed($this->markup(CardBinding::of($card), $binding));

        $this->assertSame($expected['present'], $element instanceof Element);

        if (! $element instanceof Element) {
            return;
        }

        $this->assertSame($expected['text'], $element->textContent);
        $this->assertSame($expected['elements'], $element->childElementCount);
        $this->assertSame($expected['classes'], $this->classes($element));

        foreach ($expected['attributes'] as $name => $value) {
            $this->assertSame($value, $element->getAttribute($name), $name);
        }
    }

    /**
     * @return Generator<string, array{Binding, array<string, mixed>}>
     */
    public static function renderedElements(): Generator
    {
        foreach (self::cases()['elements'] as $case) {
            if ($case['expected']['present']) {
                yield $case['case'] => [$case['binding'], $case['card']];
            }
        }
    }

    /**
     * @param  Binding  $binding
     * @param  array<string, mixed>  $card
     */
    #[DataProvider('renderedElements')]
    #[Test]
    public function a_rendered_card_carries_no_binding_instruction(array $binding, array $card): void
    {
        $element = $this->parsed($this->markup(CardBinding::of($card), $binding));

        $this->assertInstanceOf(Element::class, $element);

        foreach (BindingAttribute::cases() as $attribute) {
            $this->assertFalse($element->hasAttribute($attribute->value), $attribute->value);
        }
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function allowedAttributes(): Generator
    {
        foreach (self::cases()['attributes']['allowed'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('allowedAttributes')]
    #[Test]
    public function it_binds_an_attribute_on_the_list(string $name): void
    {
        $this->assertSame($name, CardFieldAttribute::named($name)->name);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function refusedAttributes(): Generator
    {
        foreach (self::cases()['attributes']['refused'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('refusedAttributes')]
    #[Test]
    public function it_refuses_to_render_an_attribute_off_the_list(string $name): void
    {
        $this->expectException(BindingRefused::class);

        CardBinding::template()->attributes([$name => 'brand']);
    }

    /**
     * @return Generator<string, array{string, bool}>
     */
    public static function urls(): Generator
    {
        foreach (self::cases()['urls']['safe'] as $url) {
            yield 'safe '.json_encode($url) => [$url, true];
        }

        foreach (self::cases()['urls']['unsafe'] as $url) {
            yield 'unsafe '.json_encode($url) => [$url, false];
        }
    }

    #[DataProvider('urls')]
    #[Test]
    public function it_writes_only_a_web_or_relative_url(string $url, bool $isSafe): void
    {
        $this->assertSame($isSafe, CardFieldAttribute::named('href')->accepts($url));
    }

    /**
     * @return Generator<string, array{string, bool}>
     */
    public static function urlLists(): Generator
    {
        foreach (self::cases()['urlLists']['safe'] as $list) {
            yield 'safe '.json_encode($list) => [$list, true];
        }

        foreach (self::cases()['urlLists']['unsafe'] as $list) {
            yield 'unsafe '.json_encode($list) => [$list, false];
        }
    }

    #[Test]
    public function it_writes_no_empty_value_since_an_empty_href_links_the_current_page(): void
    {
        $this->assertFalse(CardFieldAttribute::named('href')->accepts(''));
    }

    #[DataProvider('urlLists')]
    #[Test]
    public function it_writes_a_srcset_only_when_every_url_is_web_or_relative(string $list, bool $isSafe): void
    {
        $this->assertSame($isSafe, CardFieldAttribute::named('srcset')->accepts($list));
    }

    /**
     * @return Generator<string, array{string, bool}>
     */
    public static function dimensions(): Generator
    {
        foreach (self::cases()['dimensions']['accepted'] as $size) {
            yield 'accepted '.json_encode($size) => [$size, true];
        }

        foreach (self::cases()['dimensions']['refused'] as $size) {
            yield 'refused '.json_encode($size) => [$size, false];
        }
    }

    #[DataProvider('dimensions')]
    #[Test]
    public function it_writes_a_dimension_only_as_a_whole_positive_number(string $size, bool $isAccepted): void
    {
        $this->assertSame($isAccepted, CardFieldAttribute::named('width')->accepts($size));
    }

    /**
     * @return Generator<string, array{mixed, string}>
     */
    public static function texts(): Generator
    {
        foreach (self::cases()['texts'] as $case) {
            yield json_encode($case['value']).' as '.$case['text'] => [$case['value'], $case['text']];
        }
    }

    #[DataProvider('texts')]
    #[Test]
    public function it_writes_a_value_as_the_client_prints_it(mixed $value, string $text): void
    {
        $this->assertSame($text, new CardFieldValue($value)->text());
    }

    #[Test]
    public function it_writes_nothing_for_a_number_json_cannot_carry(): void
    {
        $this->assertSame('', new CardFieldValue(NAN)->text());
        $this->assertSame('', new CardFieldValue(INF)->text());
    }

    #[Test]
    public function it_adds_classes_together_and_escapes_only_what_is_not_escaped_yet(): void
    {
        $element = CardFieldElement::of(new ComponentAttributeBag(['class' => 'first']))
            ->with(['class' => 'second', 'title' => '<b>'], new ComponentAttributeBag(['class' => 'third', 'alt' => '&amp;']));

        $this->assertEqualsCanonicalizing(['first', 'second', 'third'], explode(' ', $element->attributes->get('class')));
        $this->assertSame('&lt;b&gt;', $element->attributes->get('title'));
        $this->assertSame('&amp;', $element->attributes->get('alt'));
    }

    #[Test]
    public function it_refuses_a_second_class_list_on_one_element(): void
    {
        $binding = CardBinding::template();

        $this->expectException(BindingRefused::class);

        CardFieldElement::of($binding->classList('cart_class'))->with($binding->classList('badge_class'));
    }

    #[Test]
    public function it_refuses_a_second_class_list_given_as_an_array(): void
    {
        $binding = CardBinding::template();

        $this->expectException(BindingRefused::class);

        CardFieldElement::of($binding->classList('cart_class'))->with($binding->classList('badge_class')->getAttributes());
    }

    #[Test]
    public function it_merges_one_class_list_with_other_attributes(): void
    {
        $binding = CardBinding::template();

        $element = CardFieldElement::of(new ComponentAttributeBag(['class' => 'cta']))
            ->with($binding->classList('cart_class'), $binding->attributes(['href' => 'url']));

        $this->assertSame('cart_class', $element->attributes->get(BindingAttribute::ClassList->value));
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function refusedFields(): Generator
    {
        foreach (self::cases()['fields']['refused'] as $field) {
            yield json_encode($field) => [$field];
        }
    }

    #[DataProvider('refusedFields')]
    #[Test]
    public function it_refuses_a_field_name_the_client_skips(string $field): void
    {
        $this->expectException(BindingRefused::class);

        CardBinding::template()->text($field);
    }

    #[DataProvider('refusedFields')]
    #[Test]
    public function it_refuses_a_condition_on_a_field_name_the_client_skips(string $field): void
    {
        $this->expectException(BindingRefused::class);

        CardBinding::template()->onlyWith($field);
    }

    /**
     * @param  Binding  $binding
     */
    private function markup(CardBinding $bind, array $binding): string
    {
        $field = match (true) {
            isset($binding['text']) => $bind->text($binding['text']),
            isset($binding['onlyWith']) => $bind->onlyWith($binding['onlyWith']),
            isset($binding['onlyWithout']) => $bind->onlyWithout($binding['onlyWithout']),
            isset($binding['attributes']) => CardFieldElement::of($bind->attributes($binding['attributes'])),
            isset($binding['classList']) => CardFieldElement::of($bind->classList($binding['classList'])),
            isset($binding['classes']) => CardFieldElement::of($bind->classes($binding['classes']['toggles'])),
            default => CardFieldElement::absent(),
        };

        return $field->isPresent() ? "<a {$this->attributes($field, $binding)}>{$field->toHtml()}</a>" : '';
    }

    /**
     * The markup's own classes, merged the way a view merges them into a bound element.
     *
     * @param  Binding  $binding
     */
    private function attributes(CardFieldElement $field, array $binding): ComponentAttributeBag
    {
        return $field->attributes->class($binding['classes']['always'] ?? '');
    }

    private function parsed(string $markup): ?Element
    {
        $document = HTMLDocument::createFromString('<!doctype html><body>'.$markup, LIBXML_NOERROR);

        return $document->body?->firstElementChild;
    }

    /**
     * @return array{string, list<string>, array<string, string>}
     */
    private function described(Element $element): array
    {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }

        unset($attributes['class']);
        ksort($attributes);

        return [$element->textContent ?? '', $this->classes($element), $attributes];
    }

    /**
     * @return list<string>
     */
    private function classes(Element $element): array
    {
        $classes = preg_split('/\s+/', $element->getAttribute('class') ?? '', flags: PREG_SPLIT_NO_EMPTY) ?: [];
        sort($classes);

        return $classes;
    }

    /**
     * @return array{
     *     attributes: array{allowed: list<string>, refused: list<string>},
     *     urls: array{safe: list<string>, unsafe: list<string>},
     *     urlLists: array{safe: list<string>, unsafe: list<string>},
     *     dimensions: array{accepted: list<string>, refused: list<string>},
     *     texts: list<array{value: mixed, text: string}>,
     *     fields: array{refused: list<string>},
     *     elements: list<array{
     *         case: string,
     *         binding: Binding,
     *         template: string,
     *         card: array<string, mixed>,
     *         expected: Expected
     *     }>
     * }
     */
    private static function cases(): array
    {
        return json_decode((string) file_get_contents(self::CASES), true, flags: JSON_THROW_ON_ERROR);
    }
}
