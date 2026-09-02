<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class UrlParameters
{
    public const string UNMAPPED_PREFIX = 'f_';

    /**
     * @param  array<string, string>  $mapping
     */
    public function __construct(private array $mapping) {}

    public static function fromConfig(): self
    {
        return new self(config('meilifacets.url_parameters', []));
    }

    public function for(string $taxonomy): string
    {
        // A bare taxonomy name is a public WordPress query var, which would filter twice.
        return $this->mapping[$taxonomy] ?? self::UNMAPPED_PREFIX.$taxonomy;
    }
}
