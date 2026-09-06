<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\DefaultTerms;

final readonly class FakeDefaultTerms implements DefaultTerms
{
    /**
     * @param  array<string, string>  $slugs
     */
    public function __construct(private array $slugs = []) {}

    public function slugOf(string $taxonomy): ?string
    {
        return $this->slugs[$taxonomy] ?? null;
    }
}
