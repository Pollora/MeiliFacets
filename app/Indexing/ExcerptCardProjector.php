<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use WP_Post;

final readonly class ExcerptCardProjector implements CardProjector
{
    private const int WORDPRESS_EXCERPT_LENGTH = 55;

    private const string EXCERPT_LENGTH_FILTER = 'excerpt_length';

    public function __construct(private CardProjector $card, private PostText $postText) {}

    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array
    {
        return [
            ...$this->card->project($post),
            ...$this->excerpt($post),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function excerpt(WP_Post $post): array
    {
        $summary = $this->postText->summary($post, $this->length());

        return $summary === '' ? [] : [CardField::Excerpt->value => $summary];
    }

    /** Read on every card: the theme adds its filter after the module is wired (R-171). */
    private function length(): int
    {
        return (int) apply_filters(self::EXCERPT_LENGTH_FILTER, self::WORDPRESS_EXCERPT_LENGTH);
    }
}
