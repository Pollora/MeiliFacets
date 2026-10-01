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
     * @return array<string, string|int>
     */
    private function image(WP_Post $post): array
    {
        return ImageFields::of((int) get_post_thumbnail_id($post), $this->imageSize);
    }
}
