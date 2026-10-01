<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Indexing\ImageFields;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImageFieldsTest extends TestCase
{
    use KeepsTheIndexOut;

    private const string FILE = 'meilifacets-test/jar.jpg';

    private const string MEDIUM_FILE = 'jar-medium.jpg';

    private const string SIZE = 'medium';

    private const array MEDIUM = ['file' => self::MEDIUM_FILE, 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg'];

    private int $imageId = 0;

    protected function tearDown(): void
    {
        wp_delete_post($this->imageId, true);

        parent::tearDown();
    }

    #[Test]
    public function it_offers_every_size_of_the_same_ratio_the_way_wordpress_writes_a_lazy_image(): void
    {
        $this->imageId = $this->image([self::SIZE => self::MEDIUM]);
        $uploads = wp_get_upload_dir()['baseurl'].'/meilifacets-test/';

        $fields = ImageFields::of($this->imageId, self::SIZE);

        $this->assertSame($uploads.self::MEDIUM_FILE, $fields[CardField::ImageUrl->value]);
        $this->assertSame(300, $fields[CardField::ImageWidth->value]);
        $this->assertSame(200, $fields[CardField::ImageHeight->value]);
        $this->assertSame($uploads.self::MEDIUM_FILE.' 300w, '.$uploads.'jar.jpg 600w', $fields[CardField::ImageSrcset->value]);
        $this->assertSame('auto, (max-width: 300px) 100vw, 300px', $fields[CardField::ImageSizes->value]);
        $this->assertSame('A jar', $fields[CardField::ImageAlt->value]);
    }

    #[Test]
    public function it_leaves_the_sizes_out_of_an_image_wordpress_holds_in_one_size(): void
    {
        $this->imageId = $this->image([]);

        $fields = ImageFields::of($this->imageId, self::SIZE);

        $this->assertArrayNotHasKey(CardField::ImageSrcset->value, $fields);
        $this->assertArrayNotHasKey(CardField::ImageSizes->value, $fields);
    }

    #[Test]
    public function it_lends_no_image_to_a_card_without_one_while_the_global_post_is_an_attachment(): void
    {
        $this->imageId = $this->image([self::SIZE => self::MEDIUM]);
        $globalPost = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = get_post($this->imageId);

        try {
            $this->assertSame([], ImageFields::of(0, self::SIZE));
        } finally {
            $GLOBALS['post'] = $globalPost;
        }
    }

    /**
     * @param  array<string, array{file: string, width: int, height: int, mime-type: string}>  $sizes
     */
    private function image(array $sizes): int
    {
        $imageId = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => 'Jar, for the test'], self::FILE);

        wp_update_attachment_metadata($imageId, ['width' => 600, 'height' => 400, 'file' => self::FILE, 'sizes' => $sizes]);
        update_post_meta($imageId, '_wp_attachment_image_alt', 'A jar');

        return $imageId;
    }
}
