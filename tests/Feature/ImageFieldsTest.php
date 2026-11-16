<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Indexing\ImageFields;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;

trait ImageFieldsSupport
{
    private const string FILE = 'meilifacets-test/jar.jpg';

    private const string MEDIUM_FILE = 'jar-medium.jpg';

    private const string SIZE = 'medium';

    private const array MEDIUM = ['file' => self::MEDIUM_FILE, 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg'];

    private int $imageId = 0;

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

uses(TestCase::class, KeepsTheIndexOut::class, ImageFieldsSupport::class);

afterEach(function (): void {
    wp_delete_post($this->imageId, true);
});

it('offers every size of the same ratio the way wordpress writes a lazy image', function (): void {
    $this->imageId = $this->image([self::SIZE => self::MEDIUM]);
    $uploads = wp_get_upload_dir()['baseurl'].'/meilifacets-test/';

    $fields = ImageFields::of($this->imageId, self::SIZE);

    expect($fields[CardField::ImageUrl->value])->toBe($uploads.self::MEDIUM_FILE);
    expect($fields[CardField::ImageWidth->value])->toBe(300);
    expect($fields[CardField::ImageHeight->value])->toBe(200);
    expect($fields[CardField::ImageSrcset->value])->toBe($uploads.self::MEDIUM_FILE.' 300w, '.$uploads.'jar.jpg 600w');
    expect($fields[CardField::ImageSizes->value])->toBe('auto, (max-width: 300px) 100vw, 300px');
    expect($fields[CardField::ImageAlt->value])->toBe('A jar');
});

it('leaves the sizes out of an image wordpress holds in one size', function (): void {
    $this->imageId = $this->image([]);

    $fields = ImageFields::of($this->imageId, self::SIZE);

    $this->assertArrayNotHasKey(CardField::ImageSrcset->value, $fields);
    $this->assertArrayNotHasKey(CardField::ImageSizes->value, $fields);
});

it('lends no image to a card without one while the global post is an attachment', function (): void {
    $this->imageId = $this->image([self::SIZE => self::MEDIUM]);
    $globalPost = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = get_post($this->imageId);

    try {
        expect(ImageFields::of(0, self::SIZE))->toBe([]);
    } finally {
        $GLOBALS['post'] = $globalPost;
    }
});
