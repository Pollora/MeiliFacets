<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardDocument;
use Modules\MeiliFacets\View\CardImage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CardImageTest extends TestCase
{
    #[Test]
    public function it_reads_a_projected_image(): void
    {
        $image = $this->image([
            CardField::ImageUrl->value => 'https://example.test/photo.jpg',
            CardField::ImageAlt->value => 'A jar',
            CardField::ImageWidth->value => 300,
            CardField::ImageHeight->value => 200,
        ]);

        $this->assertSame('https://example.test/photo.jpg', $image->src);
        $this->assertSame('A jar', $image->alt);
        $this->assertTrue($image->isPresent);
        $this->assertTrue($image->hasDimensions);
    }

    #[Test]
    public function it_keeps_a_src_when_the_card_carries_no_image(): void
    {
        $image = $this->image([]);

        $this->assertSame(CardImage::BLANK, $image->src);
        $this->assertFalse($image->isPresent);
    }

    #[Test]
    public function it_falls_back_to_the_product_name_when_no_alt_was_written(): void
    {
        $this->assertSame('Serum', $this->image([])->alt);
    }

    #[Test]
    public function it_would_rather_omit_a_dimension_than_guess_one(): void
    {
        $image = $this->image([CardField::ImageWidth->value => 300]);

        $this->assertSame(300, $image->width);
        $this->assertNull($image->height);
        $this->assertFalse($image->hasDimensions);
    }

    #[Test]
    public function it_ignores_a_dimension_the_index_cannot_have_meant(): void
    {
        $image = $this->image([
            CardField::ImageWidth->value => 0,
            CardField::ImageHeight->value => 'tall',
        ]);

        $this->assertNull($image->width);
        $this->assertNull($image->height);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function image(array $fields): CardImage
    {
        return CardImage::from(new CardDocument($fields), 'Serum', ImagePriority::Lazy);
    }
}
