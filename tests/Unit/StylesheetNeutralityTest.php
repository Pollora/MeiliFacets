<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The module carries behaviour, the theme carries appearance: its stylesheet names roles, never a shop's colours. */
final class StylesheetNeutralityTest extends TestCase
{
    private const string STYLESHEETS = __DIR__.'/../../resources/assets/css';

    /**
     * @return Generator<string, array{string}>
     */
    public static function stylesheets(): Generator
    {
        yield 'listing' => ['meilifacets.css'];
        yield 'site search' => ['site-search.css'];
    }

    #[DataProvider('stylesheets')]
    #[Test]
    public function it_writes_no_colour_by_its_value(string $stylesheet): void
    {
        preg_match_all('/#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i', $this->source($stylesheet), $colours);

        $this->assertSame([], $colours[0]);
    }

    #[DataProvider('stylesheets')]
    #[Test]
    public function it_names_no_typeface(string $stylesheet): void
    {
        $this->assertDoesNotMatchRegularExpression('/font-family\s*:/', $this->source($stylesheet));
    }

    #[DataProvider('stylesheets')]
    #[Test]
    public function it_never_lets_a_box_drop_out_of_the_tree(string $stylesheet): void
    {
        $this->assertDoesNotMatchRegularExpression('/display\s*:\s*contents/', $this->source($stylesheet));
    }

    private function source(string $stylesheet): string
    {
        return (string) file_get_contents(self::STYLESHEETS.'/'.$stylesheet);
    }
}
