<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Seo;

use Modules\MeiliFacets\Enums\CardField;

/**
 * One ItemList for the page rather than an itemprop on every card: a single
 * block to validate, and nothing repeated across the grid.
 */
final readonly class ItemList
{
    /** Escapes < > & ' " so no indexed value can break out of the script element. */
    private const int FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    public function __construct(private array $cards, private int $offset = 0) {}

    public function isEmpty(): bool
    {
        return $this->elements() === [];
    }

    public function toJson(): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $this->elements(),
        ], self::FLAGS);
    }

    /**
     * Positions continue across pages: the second page does not restart at 1.
     *
     * @return list<array<string, mixed>>
     */
    private function elements(): array
    {
        $elements = [];

        foreach ($this->cards as $rank => $card) {
            $url = $this->read($card, CardField::Url);

            if ($url === '') {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => $this->offset + $rank + 1,
                'url' => $url,
                'name' => $this->read($card, CardField::Title),
            ];
        }

        return $elements;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function read(array $card, CardField $field): string
    {
        $value = $card[$field->value] ?? '';

        return is_string($value) ? $value : '';
    }
}
