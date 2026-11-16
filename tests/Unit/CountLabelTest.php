<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\CountLabel;

use Modules\MeiliFacets\View\CountLabel;

const PATTERN = ':count result|:count results';

/** The same table `tests/ts/count-label.test.ts` reads: the browser must pick the form the server wrote. */
it('picks the form the language rule names', function (array $case): void {
    $expected = explode('|', str_replace(':count', (string) $case['count'], PATTERN))[$case['form']];

    expect(new CountLabel(fn (): string => $case['locale'])->of(PATTERN, $case['count']))->toBe($expected);
})->skip(! extension_loaded('intl'), 'The intl extension is required.')->with(function () {
    $cases = json_decode((string) file_get_contents(__DIR__.'/../plural-cases.json'), true, flags: JSON_THROW_ON_ERROR);

    foreach ($cases as $case) {
        yield $case['locale'].' '.$case['count'] => [$case];
    }
});

it('hands the browser a tag it accepts', function (): void {
    expect(new CountLabel(fn (): string => 'pt_BR')->languageTag())->toBe('pt-BR');
    expect(new CountLabel(fn (): string => 'pt_PT_ao90')->languageTag())->toBe('pt-PT');
    expect(new CountLabel(fn (): string => 'zh_Hans_CN')->languageTag())->toBe('zh');
    expect(new CountLabel(fn (): string => 'C')->languageTag())->toBe('en');
});

it('falls back to the one form a pattern carries', function (): void {
    expect(new CountLabel(fn (): string => 'en')->of(':count', 4))->toBe('4');
});

it('tags each language the site switches to', function (): void {
    $locale = 'de_DE';
    $countLabel = new CountLabel(function () use (&$locale): string {
        return $locale;
    });

    expect($countLabel->languageTag())->toBe('de-DE');

    $locale = 'pt_BR';

    expect($countLabel->languageTag())->toBe('pt-BR');
});
