<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Listing\Range;

final readonly class ActiveValuePatterns
{
    private function __construct(
        private string $remove,
        private string $query,
        private string $between,
        private string $from,
        private string $upTo,
    ) {}

    public static function translated(): self
    {
        return new self(
            remove: __('Remove the :label filter'),
            query: __('“:query”'),
            between: __(':min – :max'),
            from: __('From :min'),
            upTo: __('Up to :max'),
        );
    }

    public function removal(string $label): string
    {
        return strtr($this->remove, [':label' => $label]);
    }

    public function query(string $term): string
    {
        return strtr($this->query, [':query' => $term]);
    }

    public function range(Range $price, string $min, string $max): string
    {
        $written = [':min' => $min, ':max' => $max];

        if ($price->max === null) {
            return strtr($this->from, $written);
        }

        if ($price->min === null) {
            return strtr($this->upTo, $written);
        }

        return strtr($this->between, $written);
    }

    /**
     * @return array{remove: string, query: string, between: string, from: string, upTo: string}
     */
    public function toArray(): array
    {
        return [
            'remove' => $this->remove,
            'query' => $this->query,
            'between' => $this->between,
            'from' => $this->from,
            'upTo' => $this->upTo,
        ];
    }
}
