<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\ImageFields;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageFieldsTest extends TestCase
{
    #[Test]
    public function it_writes_every_image_field_once_one_is_given(): void
    {
        $fields = ImageFields::withWholeImage(['price' => '39', 'image_url' => 'variant.jpg']);

        $this->assertSame('variant.jpg', $fields['image_url']);
        $this->assertSame('', $fields['image_srcset']);
        $this->assertSame('', $fields['image_sizes']);
        $this->assertSame('39', $fields['price']);
    }

    #[Test]
    public function it_leaves_fields_without_any_image_as_they_are(): void
    {
        $this->assertSame(['price' => '39'], ImageFields::withWholeImage(['price' => '39']));
    }
}
