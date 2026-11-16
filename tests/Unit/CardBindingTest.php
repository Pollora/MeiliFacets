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
function cardBindingCases(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../card-binding-cases.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  Binding  $binding
 */
function cardBindingMarkup(CardBinding $bind, array $binding): string
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

    if (! $field->isPresent()) {
        return '';
    }

    $attrs = cardBindingAttributes($field, $binding);

    return "<a {$attrs}>{$field->toHtml()}</a>";
}

/**
 * The markup's own classes, merged the way a view merges them into a bound element.
 *
 * @param  Binding  $binding
 */
function cardBindingAttributes(CardFieldElement $field, array $binding): ComponentAttributeBag
{
    return $field->attributes->class($binding['classes']['always'] ?? '');
}

function cardBindingParsed(string $markup): ?Element
{
    $document = HTMLDocument::createFromString('<!doctype html><body>'.$markup, LIBXML_NOERROR);

    return $document->body?->firstElementChild;
}

/**
 * @return array{string, list<string>, array<string, string>}
 */
function cardBindingDescribed(Element $element): array
{
    $attributes = [];

    foreach ($element->attributes as $attribute) {
        $attributes[$attribute->name] = $attribute->value;
    }

    unset($attributes['class']);
    ksort($attributes);

    return [$element->textContent ?? '', cardBindingClasses($element), $attributes];
}

/**
 * @return list<string>
 */
function cardBindingClasses(Element $element): array
{
    $classes = preg_split('/\s+/', $element->getAttribute('class') ?? '', flags: PREG_SPLIT_NO_EMPTY) ?: [];
    sort($classes);

    return $classes;
}

/**
 * @return Generator<string, array{Binding, string}>
 */
dataset('card binding templates', function (): Generator {
    foreach (cardBindingCases()['elements'] as $case) {
        yield $case['case'] => [$case['binding'], $case['template']];
    }
});

/**
 * @return Generator<string, array{Binding, array<string, mixed>, Expected}>
 */
dataset('card binding elements', function (): Generator {
    foreach (cardBindingCases()['elements'] as $case) {
        yield $case['case'] => [$case['binding'], $case['card'], $case['expected']];
    }
});

/**
 * @return Generator<string, array{Binding, array<string, mixed>}>
 */
dataset('card binding rendered elements', function (): Generator {
    foreach (cardBindingCases()['elements'] as $case) {
        if ($case['expected']['present']) {
            yield $case['case'] => [$case['binding'], $case['card']];
        }
    }
});

/**
 * @return Generator<string, array{string}>
 */
dataset('card binding allowed attributes', function (): Generator {
    foreach (cardBindingCases()['attributes']['allowed'] as $name) {
        yield $name => [$name];
    }
});

/**
 * @return Generator<string, array{string}>
 */
dataset('card binding refused attributes', function (): Generator {
    foreach (cardBindingCases()['attributes']['refused'] as $name) {
        yield $name => [$name];
    }
});

/**
 * @return Generator<string, array{string, bool}>
 */
dataset('card binding urls', function (): Generator {
    foreach (cardBindingCases()['urls']['safe'] as $url) {
        yield 'safe '.json_encode($url) => [$url, true];
    }

    foreach (cardBindingCases()['urls']['unsafe'] as $url) {
        yield 'unsafe '.json_encode($url) => [$url, false];
    }
});

/**
 * @return Generator<string, array{string, bool}>
 */
dataset('card binding url lists', function (): Generator {
    foreach (cardBindingCases()['urlLists']['safe'] as $list) {
        yield 'safe '.json_encode($list) => [$list, true];
    }

    foreach (cardBindingCases()['urlLists']['unsafe'] as $list) {
        yield 'unsafe '.json_encode($list) => [$list, false];
    }
});

/**
 * @return Generator<string, array{string, bool}>
 */
dataset('card binding dimensions', function (): Generator {
    foreach (cardBindingCases()['dimensions']['accepted'] as $size) {
        yield 'accepted '.json_encode($size) => [$size, true];
    }

    foreach (cardBindingCases()['dimensions']['refused'] as $size) {
        yield 'refused '.json_encode($size) => [$size, false];
    }
});

/**
 * @return Generator<string, array{mixed, string}>
 */
dataset('card binding texts', function (): Generator {
    foreach (cardBindingCases()['texts'] as $case) {
        yield json_encode($case['value']).' as '.$case['text'] => [$case['value'], $case['text']];
    }
});

/**
 * @return Generator<string, array{string}>
 */
dataset('card binding refused fields', function (): Generator {
    foreach (cardBindingCases()['fields']['refused'] as $field) {
        yield json_encode($field) => [$field];
    }
});

/**
 * @param  Binding  $binding
 */
test('the template is the card rendered empty', function (array $binding, string $template): void {
    $expected = cardBindingParsed($template);
    $rendered = cardBindingParsed(cardBindingMarkup(CardBinding::template(), $binding));

    expect($expected)->toBeInstanceOf(Element::class);
    expect($rendered)->toBeInstanceOf(Element::class);
    expect(cardBindingDescribed($rendered))->toBe(cardBindingDescribed($expected));
})->with('card binding templates');

/**
 * @param  Binding  $binding
 * @param  array<string, mixed>  $card
 * @param  Expected  $expected
 */
it('renders what the client writes', function (array $binding, array $card, array $expected): void {
    $element = cardBindingParsed(cardBindingMarkup(CardBinding::of($card), $binding));

    expect($element instanceof Element)->toBe($expected['present']);

    if (! $element instanceof Element) {
        return;
    }

    expect($element->textContent)->toBe($expected['text']);
    expect($element->childElementCount)->toBe($expected['elements']);
    expect(cardBindingClasses($element))->toBe($expected['classes']);

    foreach ($expected['attributes'] as $name => $value) {
        $this->assertSame($value, $element->getAttribute($name), $name);
    }
})->with('card binding elements');

/**
 * @param  Binding  $binding
 * @param  array<string, mixed>  $card
 */
test('a rendered card carries no binding instruction', function (array $binding, array $card): void {
    $element = cardBindingParsed(cardBindingMarkup(CardBinding::of($card), $binding));

    expect($element)->toBeInstanceOf(Element::class);

    foreach (BindingAttribute::cases() as $attribute) {
        $this->assertFalse($element->hasAttribute($attribute->value), $attribute->value);
    }
})->with('card binding rendered elements');

it('binds an attribute on the list', function (string $name): void {
    expect(CardFieldAttribute::named($name)->name)->toBe($name);
})->with('card binding allowed attributes');

it('refuses to render an attribute off the list', function (string $name): void {
    $this->expectException(BindingRefused::class);

    CardBinding::template()->attributes([$name => 'brand']);
})->with('card binding refused attributes');

it('writes only a web or relative url', function (string $url, bool $isSafe): void {
    expect(CardFieldAttribute::named('href')->accepts($url))->toBe($isSafe);
})->with('card binding urls');

it('writes no empty value since an empty href links the current page', function (): void {
    expect(CardFieldAttribute::named('href')->accepts(''))->toBeFalse();
});

it('writes a srcset only when every url is web or relative', function (string $list, bool $isSafe): void {
    expect(CardFieldAttribute::named('srcset')->accepts($list))->toBe($isSafe);
})->with('card binding url lists');

it('writes a dimension only as a whole positive number', function (string $size, bool $isAccepted): void {
    expect(CardFieldAttribute::named('width')->accepts($size))->toBe($isAccepted);
})->with('card binding dimensions');

it('writes a value as the client prints it', function (mixed $value, string $text): void {
    expect(new CardFieldValue($value)->text())->toBe($text);
})->with('card binding texts');

it('writes nothing for a number json cannot carry', function (): void {
    expect(new CardFieldValue(NAN)->text())->toBe('');
    expect(new CardFieldValue(INF)->text())->toBe('');
});

it('adds classes together and escapes only what is not escaped yet', function (): void {
    $element = CardFieldElement::of(new ComponentAttributeBag(['class' => 'first']))
        ->with(['class' => 'second', 'title' => '<b>'], new ComponentAttributeBag(['class' => 'third', 'alt' => '&amp;']));

    $this->assertEqualsCanonicalizing(['first', 'second', 'third'], explode(' ', $element->attributes->get('class')));
    expect($element->attributes->get('title'))->toBe('&lt;b&gt;');
    expect($element->attributes->get('alt'))->toBe('&amp;');
});

it('refuses a second class list on one element', function (): void {
    $binding = CardBinding::template();

    $this->expectException(BindingRefused::class);

    CardFieldElement::of($binding->classList('cart_class'))->with($binding->classList('badge_class'));
});

it('refuses a second class list given as an array', function (): void {
    $binding = CardBinding::template();

    $this->expectException(BindingRefused::class);

    CardFieldElement::of($binding->classList('cart_class'))->with($binding->classList('badge_class')->getAttributes());
});

it('merges one class list with other attributes', function (): void {
    $binding = CardBinding::template();

    $element = CardFieldElement::of(new ComponentAttributeBag(['class' => 'cta']))
        ->with($binding->classList('cart_class'), $binding->attributes(['href' => 'url']));

    expect($element->attributes->get(BindingAttribute::ClassList->value))->toBe('cart_class');
});

it('refuses a field name the client skips', function (string $field): void {
    $this->expectException(BindingRefused::class);

    CardBinding::template()->text($field);
})->with('card binding refused fields');

it('refuses a condition on a field name the client skips', function (string $field): void {
    $this->expectException(BindingRefused::class);

    CardBinding::template()->onlyWith($field);
})->with('card binding refused fields');
