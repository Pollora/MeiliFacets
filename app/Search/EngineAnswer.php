<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

/**
 * One search the engine answered: what it does not hold, or holds as something else, reads as nothing.
 */
final readonly class EngineAnswer
{
    private const string HITS = 'hits';

    private const string TOTAL_HITS = 'totalHits';

    private const string FACET_DISTRIBUTION = 'facetDistribution';

    private const string FACET_STATS = 'facetStats';

    /**
     * @param  array<mixed>  $answer
     */
    public function __construct(private array $answer = []) {}

    /**
     * @return list<array<mixed>>
     */
    public function hits(): array
    {
        return array_values(array_filter($this->arrayIn($this->answer, self::HITS), is_array(...)));
    }

    public function total(): int
    {
        $total = $this->answer[self::TOTAL_HITS] ?? null;

        return is_numeric($total) ? (int) $total : 0;
    }

    /**
     * @return array<array-key, int> value to count
     */
    public function distribution(string $field): array
    {
        return array_filter($this->arrayIn($this->arrayIn($this->answer, self::FACET_DISTRIBUTION), $field), is_int(...));
    }

    /**
     * @return array<array-key, array<array-key, int>> field to value to count
     */
    public function distributions(): array
    {
        $fields = array_keys($this->arrayIn($this->answer, self::FACET_DISTRIBUTION));

        return array_combine($fields, array_map(fn (int|string $field): array => $this->distribution((string) $field), $fields));
    }

    /**
     * @return array<array-key, array<array-key, float>> numeric field to its min and max
     */
    public function stats(): array
    {
        return array_map(
            static fn (mixed $stats): array => array_filter(is_array($stats) ? $stats : [], static fn (mixed $bound): bool => is_int($bound) || is_float($bound)),
            $this->arrayIn($this->answer, self::FACET_STATS),
        );
    }

    /**
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    private function arrayIn(array $array, string $key): array
    {
        $value = $array[$key] ?? null;

        return is_array($value) ? $value : [];
    }
}
