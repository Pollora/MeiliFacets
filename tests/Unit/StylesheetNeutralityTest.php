<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The module carries behaviour, the theme carries appearance: its stylesheet names roles, never a shop's colours. */
final class StylesheetNeutralityTest extends TestCase
{
    private const string STYLESHEET = __DIR__.'/../../resources/assets/css/meilifacets.css';

    #[Test]
    public function it_writes_no_colour_by_its_value(): void
    {
        preg_match_all('/#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i', $this->source(), $colours);

        $this->assertSame([], $colours[0]);
    }

    #[Test]
    public function it_names_no_typeface(): void
    {
        $this->assertDoesNotMatchRegularExpression('/font-family\s*:/', $this->source());
    }

    private function source(): string
    {
        return (string) file_get_contents(self::STYLESHEET);
    }
}
