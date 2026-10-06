<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Support\PlainText;

final readonly class ImageFields
{
    private const int NO_IMAGE = 0;

    private const string ALT_META = '_wp_attachment_image_alt';

    private const string AUTO_SIZES_FILTER = 'wp_img_tag_add_auto_sizes';

    private const string AUTO_SIZES = 'auto, ';

    private const array NO_FIELD = [
        CardField::ImageUrl->value => '',
        CardField::ImageAlt->value => '',
        CardField::ImageWidth->value => '',
        CardField::ImageHeight->value => '',
        CardField::ImageSrcset->value => '',
        CardField::ImageSizes->value => '',
    ];

    /**
     * Every image field, empty where this image has none.
     *
     * @return array<string, string|int>
     */
    public static function whole(int $imageId, string $size): array
    {
        $fields = self::of($imageId, $size);

        return $fields === [] ? [] : [...self::NO_FIELD, ...$fields];
    }

    /**
     * Card fields holding part of an image hold all of it, so that none of another image's fields stays under them.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function withWholeImage(array $fields): array
    {
        return array_intersect_key($fields, self::NO_FIELD) === [] ? $fields : [...self::NO_FIELD, ...$fields];
    }

    /**
     * @return array<string, string|int>
     */
    public static function of(int $imageId, string $size): array
    {
        // `get_post(0)` falls back to the global post: an attachment page would lend its own image.
        if ($imageId <= self::NO_IMAGE) {
            return [];
        }

        $image = wp_get_attachment_image_src($imageId, $size);

        if (! is_array($image)) {
            return [];
        }

        [$url, $width, $height] = $image;

        return [
            CardField::ImageUrl->value => (string) $url,
            CardField::ImageAlt->value => PlainText::from((string) get_post_meta($imageId, self::ALT_META, true)),
            CardField::ImageWidth->value => (int) $width,
            CardField::ImageHeight->value => (int) $height,
            ...self::candidates($imageId, $size),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function candidates(int $imageId, string $size): array
    {
        $srcset = (string) wp_get_attachment_image_srcset($imageId, $size);
        $sizes = (string) wp_get_attachment_image_sizes($imageId, $size);

        if ($srcset === '' || $sizes === '') {
            return [];
        }

        return [CardField::ImageSrcset->value => $srcset, CardField::ImageSizes->value => self::lazySizes($sizes)];
    }

    /** WordPress adds `auto` to a lazy image's sizes; a browser ignores it on an image loaded eagerly. */
    private static function lazySizes(string $sizes): string
    {
        $addsAuto = (bool) apply_filters(self::AUTO_SIZES_FILTER, true);

        return $addsAuto ? self::AUTO_SIZES.$sizes : $sizes;
    }
}
