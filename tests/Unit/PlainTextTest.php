<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\PlainText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlainTextTest extends TestCase
{
    #[Test]
    public function it_decodes_what_wordpress_stores_encoded(): void
    {
        $this->assertSame('Crème & Soins', PlainText::from('Crème &amp; Soins'));
    }

    #[Test]
    public function it_decodes_the_numeric_entity_wptexturize_emits(): void
    {
        $this->assertSame('Éclat & Vitamine', PlainText::from('Éclat &#038; Vitamine'));
    }

    #[Test]
    public function it_leaves_plain_accented_text_untouched(): void
    {
        $this->assertSame('Crème « bio »', PlainText::from('Crème « bio »'));
    }

    #[Test]
    public function it_decodes_quotes_so_an_attribute_is_not_double_encoded(): void
    {
        $this->assertSame('5" band', PlainText::from('5&quot; band'));
        $this->assertSame("L'Occitane", PlainText::from('L&#039;Occitane'));
    }
}
