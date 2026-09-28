<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

final readonly class SearchSettings
{
    public const int DEFAULT_MIN_CHARS = 2;

    public const int DEFAULT_DELAY = 120;

    public const int DEFAULT_LIMIT = 4;

    /**
     * @param  int  $minChars  characters typed before the first search
     * @param  int  $delay  milliseconds of quiet typing before a search leaves
     * @param  int  $limit  results per section, unless the section says otherwise
     */
    public function __construct(
        public int $minChars = self::DEFAULT_MIN_CHARS,
        public int $delay = self::DEFAULT_DELAY,
        public int $limit = self::DEFAULT_LIMIT,
    ) {}

    public function withMinChars(int $minChars): self
    {
        return new self($minChars, $this->delay, $this->limit);
    }

    public function withDelay(int $delay): self
    {
        return new self($this->minChars, $delay, $this->limit);
    }
}
