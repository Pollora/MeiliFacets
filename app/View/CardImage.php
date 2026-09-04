<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\ImagePriority;

final readonly class CardImage
{
    /**
     * A src-less <img> is invalid markup, and browsers request the current URL
     * for it. The node itself stays: an empty slot is revealed, never created.
     */
    public const string BLANK = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private function __construct(
        public string $src,
        public string $alt,
        public ?int $width,
        public ?int $height,
        public ImagePriority $priority,
        public bool $isPresent,
        public bool $hasDimensions,
    ) {}

    /**
     * An image carries no title of its own: a card without an alt text falls
     * back to the one thing that names the product.
     */
    public static function from(CardDocument $card, string $fallbackAlt, ImagePriority $priority): self
    {
        $url = $card->text(CardField::ImageUrl);
        $width = $card->size(CardField::ImageWidth);
        $height = $card->size(CardField::ImageHeight);

        return new self(
            src: $url ?: self::BLANK,
            alt: $card->text(CardField::ImageAlt) ?: $fallbackAlt,
            width: $width,
            height: $height,
            priority: $priority,
            isPresent: $url !== '',
            hasDimensions: $width !== null && $height !== null,
        );
    }
}
