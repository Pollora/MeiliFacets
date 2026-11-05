<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

final readonly class HiddenField
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}

    public static function fromQueryPair(string $pair): self
    {
        [$name, $value] = [...explode('=', $pair, 2), ''];

        return new self(urldecode($name), urldecode($value));
    }
}
