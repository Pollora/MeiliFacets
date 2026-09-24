<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Search\FilterExpression;

final readonly class SortFilter
{
    private const string TRUE = 'true';

    public function __construct(
        public string $field,
        public string $value,
    ) {}

    public static function whereTrue(string $field): self
    {
        return new self($field, self::TRUE);
    }

    /**
     * @param  array<string, Sort>  $sorts
     * @return array<string, self>
     */
    public static function carriedBy(array $sorts): array
    {
        $filters = [];

        foreach ($sorts as $key => $sort) {
            if ($sort->isFiltering()) {
                $filters[$key] = $sort->filter;
            }
        }

        return $filters;
    }

    public function clause(): string
    {
        return FilterExpression::equals($this->field, $this->value);
    }

    /**
     * @param  array<string, array<string, int>>  $distribution  field to value to count
     */
    public function matchesIn(array $distribution): int
    {
        return (int) ($distribution[$this->field][$this->value] ?? 0);
    }

    /**
     * @return array{field: string, value: string}
     */
    public function describe(): array
    {
        return ['field' => $this->field, 'value' => $this->value];
    }
}
