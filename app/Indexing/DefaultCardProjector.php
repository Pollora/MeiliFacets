<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use WP_Post;

final readonly class DefaultCardProjector implements CardProjector
{
    public const string DEFAULT_IMAGE_SIZE = 'medium';

    private const int NO_IMAGE = 0;

    private const string ALT_META = '_wp_attachment_image_alt';

    public function __construct(private string $imageSize) {}

    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array
    {
        return [
            CardField::Title->value => get_the_title($post),
            CardField::Url->value => (string) get_permalink($post),
            ...$this->image($post),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function image(WP_Post $post): array
    {
        $imageId = (int) get_post_thumbnail_id($post);

        if ($imageId === self::NO_IMAGE) {
            return [];
        }

        return [
            CardField::ImageUrl->value => (string) wp_get_attachment_image_url($imageId, $this->imageSize),
            CardField::ImageAlt->value => (string) get_post_meta($imageId, self::ALT_META, true),
        ];
    }
}
