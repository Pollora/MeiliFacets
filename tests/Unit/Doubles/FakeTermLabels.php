<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\TermLabels;

final class FakeTermLabels implements TermLabels
{
    public int $lookups = 0;

    /**
     * @param  array<string, string>  $labels
     */
    public function __construct(private readonly array $labels = []) {}

    /**
     * @param  list<string>  $slugs
     * @return array<string, string>
     */
    public function of(string $taxonomy, array $slugs): array
    {
        $this->lookups++;

        return array_intersect_key($this->labels, array_flip($slugs));
    }
}
