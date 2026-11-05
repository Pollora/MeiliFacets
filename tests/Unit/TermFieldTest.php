<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\TermField;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TermFieldTest extends TestCase
{
    #[Test]
    public function it_reads_the_text_held_under_its_key(): void
    {
        $term = ['slug' => 'acme', 'taxonomy' => 'product_brand'];

        $this->assertSame('acme', TermField::Slug->textIn($term));
        $this->assertSame('product_brand', TermField::Taxonomy->textIn($term));
    }

    #[Test]
    public function it_reads_nothing_from_a_missing_or_empty_key(): void
    {
        $this->assertNull(TermField::Slug->textIn(['name' => 'Acme']));
        $this->assertNull(TermField::Slug->textIn(['slug' => '']));
    }

    /** A term read from the database carries its ids as numbers, and a broken one may carry anything. */
    #[Test]
    public function it_reads_nothing_from_a_value_that_is_not_text(): void
    {
        $this->assertNull(TermField::TermId->textIn(['term_id' => 12]));
        $this->assertNull(TermField::Slug->textIn(['slug' => null]));
        $this->assertNull(TermField::Slug->textIn(['slug' => ['acme']]));
    }
}
