<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Support\PlainText;
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
            CardField::Title->value => PlainText::from(get_the_title($post)),
            CardField::Url->value => (string) get_permalink($post),
            ...$this->image($post),
        ];
    }

    /**
     * Intrinsic dimensions ship with the URL: the card reserves its space before
     * any stylesheet loads, and the layout never shifts once the image arrives.
     *
     * @return array<string, string|int>
     */
    private function image(WP_Post $post): array
    {
        $imageId = (int) get_post_thumbnail_id($post);

        if ($imageId === self::NO_IMAGE) {
            return [];
        }

        $image = wp_get_attachment_image_src($imageId, $this->imageSize);

        if (! is_array($image)) {
            return [];
        }

        [$url, $width, $height] = $image;

        return [
            CardField::ImageUrl->value => (string) $url,
            CardField::ImageAlt->value => PlainText::from((string) get_post_meta($imageId, self::ALT_META, true)),
            CardField::ImageWidth->value => (int) $width,
            CardField::ImageHeight->value => (int) $height,
        ];
    }
}
