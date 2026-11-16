<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Contracts\PageMetadata;
use Modules\MeiliFacets\View\DocumentTitle;
use Modules\MeiliFacets\View\WordPressPageMetadata;
use Modules\MeiliFacets\View\YoastCanonicals;
use Modules\MeiliFacets\View\YoastPageMetadata;
use Modules\MeiliFacets\View\YoastPageWords;
use Tests\TestCase;

trait PageMetadataSupport
{
    private const string SHOP = 'https://shop.test/shop';

    private const string TITLE_SHORT_CIRCUIT = 'pre_get_document_title';

    /**
     * @return array{first: string, paged: string}
     */
    private function byPage(string $first, string $paged): array
    {
        return ['first' => $first, 'paged' => $paged];
    }

    private function yoast(): YoastPageMetadata
    {
        return new YoastPageMetadata(new DocumentTitle, new YoastPageWords, new YoastCanonicals);
    }

    private function wordPress(): WordPressPageMetadata
    {
        return new WordPressPageMetadata(new DocumentTitle);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  Closure(): (array{first: string, paged: string}|null)  $read
     * @return array{first: string, paged: string}|null
     */
    private function withPresentation(array $properties, Closure $read): ?array
    {
        $presented = static function (object $presentation) use ($properties): object {
            $presented = clone $presentation;

            foreach (['robots' => ['index' => 'index'], ...$properties] as $name => $value) {
                $presented->{$name} = $value;
            }

            return $presented;
        };
        add_filter('wpseo_frontend_presentation', $presented);

        try {
            return $read();
        } finally {
            remove_filter('wpseo_frontend_presentation', $presented);
        }
    }

    /**
     * @param  Closure(): (array{first: string, paged: string}|null)  $read
     * @return array{first: string, paged: string}|null
     */
    private function onAnArchive(Closure $read): ?array
    {
        return $this->whileTheMainQueryHas(['is_singular' => false], $read);
    }

    /**
     * @param  Closure(): (array{first: string, paged: string}|null)  $read
     * @return array{first: string, paged: string}|null
     */
    private function onASingularPage(Closure $read): ?array
    {
        return $this->whileTheMainQueryHas(['is_singular' => true], $read);
    }

    /**
     * @param  array<string, mixed>  $flags
     * @param  Closure(): (array{first: string, paged: string}|null)  $read
     * @return array{first: string, paged: string}|null
     */
    private function whileTheMainQueryHas(array $flags, Closure $read): ?array
    {
        global $wp_query;

        $was = array_map(static fn (string $flag): mixed => $wp_query->{$flag}, array_combine(array_keys($flags), array_keys($flags)));

        foreach ($flags as $flag => $value) {
            $wp_query->{$flag} = $value;
        }

        try {
            return $read();
        } finally {
            foreach ($was as $flag => $value) {
                $wp_query->{$flag} = $value;
            }
        }
    }

    /**
     * @param  Closure(): (array{first: string, paged: string}|null)  $read
     * @return array{first: string, paged: string}|null
     */
    private function withoutTitlePlugin(Closure $read): ?array
    {
        global $wp_filter;

        $plugins = $wp_filter[self::TITLE_SHORT_CIRCUIT] ?? null;
        unset($wp_filter[self::TITLE_SHORT_CIRCUIT]);

        try {
            return $read();
        } finally {
            if ($plugins !== null) {
                $wp_filter[self::TITLE_SHORT_CIRCUIT] = $plugins;
            }
        }
    }

    private function leftAFilterBehind(string $hook): bool
    {
        global $wp_filter;

        return isset($wp_filter[$hook]->callbacks[PHP_INT_MAX]);
    }
}

/**
 * The test's current page says nothing of pages: each test hands Yoast the presentation it needs, through the filter
 * Yoast applies to it, and puts the main query back as it found it.
 */
uses(TestCase::class, PageMetadataSupport::class);

test('yoast writes a title for the first page and one for the others', function (): void {
    $titles = $this->withPresentation(['title' => 'Shop %%page%% %%sep%% Site'], fn (): ?array => $this->yoast()->titles());

    expect($titles)->toBe($this->byPage('Shop - Site', 'Shop - Page :page sur :pages - Site'));
    expect($this->leftAFilterBehind('wpseo_replacements'))->toBeFalse();
});

test('yoast titles are plain text', function (): void {
    $titles = $this->withPresentation(['title' => 'L’Atelier & Cie %%page%%'], fn (): ?array => $this->yoast()->titles());

    expect($titles['first'] ?? null)->toBe('L’Atelier & Cie');
});

test('yoast pages the canonical of an archive', function (): void {
    $canonicals = $this->onAnArchive(fn (): ?array => $this->withPresentation(
        ['canonical' => self::SHOP, 'permalink' => self::SHOP],
        fn (): ?array => $this->yoast()->canonicals(),
    ));

    expect($canonicals)->toBe($this->byPage(self::SHOP, self::SHOP.'/page/:page'));
});

test('yoast keeps the canonical of a singular page on every page', function (): void {
    $canonicals = $this->onASingularPage(fn (): ?array => $this->withPresentation(
        ['canonical' => self::SHOP, 'permalink' => self::SHOP],
        fn (): ?array => $this->yoast()->canonicals(),
    ));

    expect($canonicals)->toBe($this->byPage(self::SHOP, self::SHOP));
});

test('yoast tells nothing of a canonical a filter rewrote', function (): void {
    $rewrite = static fn (): string => 'https://elsewhere.test/';
    add_filter('wpseo_canonical', $rewrite);

    try {
        $canonicals = $this->onAnArchive(fn (): ?array => $this->withPresentation(
            ['canonical' => self::SHOP, 'permalink' => self::SHOP],
            fn (): ?array => $this->yoast()->canonicals(),
        ));
    } finally {
        remove_filter('wpseo_canonical', $rewrite);
    }

    expect($canonicals)->toBeNull();
});

test('yoast tells nothing on a page it prints no canonical for', function (): void {
    $canonicals = $this->onAnArchive(fn (): ?array => $this->withPresentation(
        ['canonical' => self::SHOP, 'permalink' => self::SHOP, 'robots' => ['index' => 'noindex']],
        fn (): ?array => $this->yoast()->canonicals(),
    ));

    expect($canonicals)->toBeNull();
});

test('wordpress alone places the page right after the title', function (): void {
    $titles = $this->withoutTitlePlugin(fn (): ?array => $this->wordPress()->titles());
    $page = preg_quote(sprintf(__('Page %s', 'default'), PageMetadata::PAGE_PLACEHOLDER), '/');

    expect($titles)->not->toBeNull();
    $this->assertStringNotContainsString(PageMetadata::PAGE_PLACEHOLDER, $titles['first']);
    expect($titles['paged'])->toMatch('/'.$page.' \S+ /u');
    expect(preg_replace('/'.$page.' \S+ /u', '', $titles['paged']))->toBe($titles['first']);
});

test('wordpress alone tells no title when a plugin writes it', function (): void {
    $plugin = static fn (): string => 'Written elsewhere';
    add_filter(self::TITLE_SHORT_CIRCUIT, $plugin);

    try {
        expect($this->wordPress()->titles())->toBeNull();
    } finally {
        remove_filter(self::TITLE_SHORT_CIRCUIT, $plugin);
    }
});

test('wordpress alone prints no canonical on an archive', function (): void {
    expect($this->onAnArchive(fn (): ?array => $this->wordPress()->canonicals()))->toBeNull();
});
