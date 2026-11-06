<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Contracts\PageMetadata;

final readonly class YoastPageMetadata implements PageMetadata
{
    public function __construct(
        private DocumentTitle $title,
        private YoastPageWords $words,
        private YoastCanonicals $canonicals,
    ) {}

    public function titles(): ?array
    {
        return [
            'first' => $this->titleWithPageWords(''),
            'paged' => $this->titleWithPageWords($this->words->withPlaceholders()),
        ];
    }

    public function canonicals(): ?array
    {
        return $this->canonicals->byPage();
    }

    private function titleWithPageWords(string $words): string
    {
        return $this->title->renderedWith('wpseo_replacements', static fn (array $replacements): array => [
            ...$replacements,
            YoastPageWords::VARIABLE => $words,
            YoastPageWords::NUMBER_VARIABLE => self::PAGE_PLACEHOLDER,
            YoastPageWords::TOTAL_VARIABLE => self::PAGE_COUNT_PLACEHOLDER,
        ]);
    }
}
