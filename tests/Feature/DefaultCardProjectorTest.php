<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Indexing\DefaultCardProjector;
use Modules\MeiliFacets\Tests\Feature\Concerns\KeepsTheIndexOut;
use Tests\TestCase;
use WP_Post;

trait DefaultCardProjectorSupport
{
    private const string LINK_FILTER = 'post_link';

    private const string THUMBNAIL_FILTER = 'post_thumbnail_id';

    private const string LINK = 'https://shop.test/journal/huile-et-baume';

    private const string FILE = 'meilifacets-test/jar.jpg';

    private const string THUMBNAIL_FILE = 'jar-thumbnail.jpg';

    private const string MEDIUM_FILE = 'jar-medium.jpg';

    private const string THUMBNAIL_SIZE = 'thumbnail';

    private const string MEDIUM_SIZE = 'medium';

    private const int UNSAVED_POST = 987654321;

    private int $imageId = 0;

    private function projector(string $imageSize): DefaultCardProjector
    {
        return new DefaultCardProjector($imageSize);
    }

    private function article(): WP_Post
    {
        return new WP_Post((object) [
            'ID' => self::UNSAVED_POST,
            'post_title' => 'Huile &amp; baume',
            'post_type' => 'post',
            'post_status' => 'publish',
            // Without it, `get_post()` reloads the post from the database, where it does not exist.
            'filter' => 'raw',
        ]);
    }

    /**
     * @param  callable(): array<string, mixed>  $projection
     * @return array<string, mixed>
     */
    private function linked(callable $projection): array
    {
        $link = static fn (): string => self::LINK;
        add_filter(self::LINK_FILTER, $link);

        try {
            return $projection();
        } finally {
            remove_filter(self::LINK_FILTER, $link);
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $projection
     * @return array<string, mixed>
     */
    private function illustrated(callable $projection): array
    {
        $imageId = $this->imageId;
        $featured = static fn (): int => $imageId;
        add_filter(self::THUMBNAIL_FILTER, $featured);

        try {
            return $projection();
        } finally {
            remove_filter(self::THUMBNAIL_FILTER, $featured);
        }
    }

    private function image(): int
    {
        $imageId = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => 'Jar, for the test'], self::FILE);

        wp_update_attachment_metadata($imageId, [
            'width' => 600,
            'height' => 400,
            'file' => self::FILE,
            'sizes' => [
                self::THUMBNAIL_SIZE => ['file' => self::THUMBNAIL_FILE, 'width' => 150, 'height' => 100, 'mime-type' => 'image/jpeg'],
                self::MEDIUM_SIZE => ['file' => self::MEDIUM_FILE, 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg'],
            ],
        ]);

        return $imageId;
    }
}

/** The post lives in memory; only its image is an attachment, deleted after each test. */
uses(TestCase::class, KeepsTheIndexOut::class, DefaultCardProjectorSupport::class);

afterEach(function (): void {
    if ($this->imageId > 0) {
        wp_delete_post($this->imageId, true);
    }
});

it('gives a post without image its plain title and its wordpress link only', function (): void {
    $card = $this->linked(fn (): array => $this->projector(self::MEDIUM_SIZE)->project($this->article()));

    expect($card)->toBe([CardField::Title->value => 'Huile & baume', CardField::Url->value => self::LINK]);
});

it('shows the featured image in the size it was given', function (): void {
    $this->imageId = $this->image();

    $thumbnail = $this->illustrated(fn (): array => $this->projector(self::THUMBNAIL_SIZE)->project($this->article()));
    $medium = $this->illustrated(fn (): array => $this->projector(self::MEDIUM_SIZE)->project($this->article()));

    expect($thumbnail[CardField::ImageUrl->value])->toEndWith(self::THUMBNAIL_FILE);
    expect($medium[CardField::ImageUrl->value])->toEndWith(self::MEDIUM_FILE);
});
