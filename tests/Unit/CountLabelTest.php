<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\CountLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CountLabelTest extends TestCase
{
    private const string PATTERN = ':count result|:count results';

    /**
     * The same table `tests/ts/count-label.test.ts` reads: the browser must pick the form the server wrote.
     *
     * @param  array{locale: string, count: int, form: int}  $case
     */
    #[DataProvider('pluralCases')]
    #[RequiresPhpExtension('intl')]
    #[Test]
    public function it_picks_the_form_the_language_rule_names(array $case): void
    {
        $expected = explode('|', str_replace(':count', (string) $case['count'], self::PATTERN))[$case['form']];

        $this->assertSame($expected, new CountLabel($case['locale'])->of(self::PATTERN, $case['count']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function pluralCases(): iterable
    {
        $cases = json_decode((string) file_get_contents(__DIR__.'/../plural-cases.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($cases as $case) {
            yield $case['locale'].' '.$case['count'] => [$case];
        }
    }

    #[Test]
    public function it_hands_the_browser_a_tag_it_accepts(): void
    {
        $this->assertSame('pt-BR', new CountLabel('pt_BR')->locale);
        $this->assertSame('pt-PT', new CountLabel('pt_PT_ao90')->locale);
        $this->assertSame('zh', new CountLabel('zh_Hans_CN')->locale);
        $this->assertSame('en', new CountLabel('C')->locale);
    }

    #[Test]
    public function it_falls_back_to_the_one_form_a_pattern_carries(): void
    {
        $this->assertSame('4', new CountLabel('en')->of(':count', 4));
    }
}
