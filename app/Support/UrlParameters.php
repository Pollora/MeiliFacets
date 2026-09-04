<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

use Modules\MeiliFacets\Enums\QueryParameter;

final readonly class UrlParameters
{
    public const string UNMAPPED_PREFIX = 'f_';

    /**
     * @param  array<string, string>  $taxonomies
     * @param  array<string, string>  $overrides
     */
    public function __construct(private array $taxonomies, private array $overrides = []) {}

    public static function fromConfig(): self
    {
        return new self(
            config('meilifacets.url_parameters', []),
            config('meilifacets.query_parameters', []),
        );
    }

    public function for(string $taxonomy): string
    {
        // A bare taxonomy name is a public WordPress query var, which would filter twice.
        return $this->taxonomies[$taxonomy] ?? self::UNMAPPED_PREFIX.$taxonomy;
    }

    public function reserved(QueryParameter $parameter): string
    {
        return $this->overrides[$parameter->value] ?? $parameter->value;
    }

    /**
     * Names taxonomies were mapped to, without the reserved ones.
     *
     * @return list<string>
     */
    public function taxonomies(): array
    {
        return array_values($this->taxonomies);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return [
            ...$this->taxonomies(),
            ...array_map($this->reserved(...), QueryParameter::cases()),
        ];
    }
}
