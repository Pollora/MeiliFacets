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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The test's current page says nothing of pages: each test hands Yoast the presentation it needs, through the filter
 * Yoast applies to it, and puts the main query back as it found it.
 */
final class PageMetadataTest extends TestCase
{
    private const string SHOP = 'https://shop.test/shop';

    private const string TITLE_SHORT_CIRCUIT = 'pre_get_document_title';

    #[Test]
    public function yoast_writes_a_title_for_the_first_page_and_one_for_the_others(): void
    {
        $titles = $this->withPresentation(['title' => 'Shop %%page%% %%sep%% Site'], fn (): ?array => $this->yoast()->titles());

        $this->assertSame($this->byPage('Shop - Site', 'Shop - Page :page sur :pages - Site'), $titles);
        $this->assertFalse($this->leftAFilterBehind('wpseo_replacements'));
    }

    #[Test]
    public function yoast_titles_are_plain_text(): void
    {
        $titles = $this->withPresentation(['title' => 'L’Atelier & Cie %%page%%'], fn (): ?array => $this->yoast()->titles());

        $this->assertSame('L’Atelier & Cie', $titles['first'] ?? null);
    }

    #[Test]
    public function yoast_pages_the_canonical_of_an_archive(): void
    {
        $canonicals = $this->onAnArchive(fn (): ?array => $this->withPresentation(
            ['canonical' => self::SHOP, 'permalink' => self::SHOP],
            fn (): ?array => $this->yoast()->canonicals(),
        ));

        $this->assertSame($this->byPage(self::SHOP, self::SHOP.'/page/:page'), $canonicals);
    }

    #[Test]
    public function yoast_keeps_the_canonical_of_a_singular_page_on_every_page(): void
    {
        $canonicals = $this->onASingularPage(fn (): ?array => $this->withPresentation(
            ['canonical' => self::SHOP, 'permalink' => self::SHOP],
            fn (): ?array => $this->yoast()->canonicals(),
        ));

        $this->assertSame($this->byPage(self::SHOP, self::SHOP), $canonicals);
    }

    #[Test]
    public function yoast_tells_nothing_of_a_canonical_a_filter_rewrote(): void
    {
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

        $this->assertNull($canonicals);
    }

    #[Test]
    public function yoast_tells_nothing_on_a_page_it_prints_no_canonical_for(): void
    {
        $canonicals = $this->onAnArchive(fn (): ?array => $this->withPresentation(
            ['canonical' => self::SHOP, 'permalink' => self::SHOP, 'robots' => ['index' => 'noindex']],
            fn (): ?array => $this->yoast()->canonicals(),
        ));

        $this->assertNull($canonicals);
    }

    #[Test]
    public function wordpress_alone_places_the_page_right_after_the_title(): void
    {
        $titles = $this->withoutTitlePlugin(fn (): ?array => $this->wordPress()->titles());
        $page = preg_quote(sprintf(__('Page %s', 'default'), PageMetadata::PAGE_PLACEHOLDER), '/');

        $this->assertNotNull($titles);
        $this->assertStringNotContainsString(PageMetadata::PAGE_PLACEHOLDER, $titles['first']);
        $this->assertMatchesRegularExpression('/'.$page.' \S+ /u', $titles['paged']);
        $this->assertSame($titles['first'], preg_replace('/'.$page.' \S+ /u', '', $titles['paged']));
    }

    #[Test]
    public function wordpress_alone_tells_no_title_when_a_plugin_writes_it(): void
    {
        $plugin = static fn (): string => 'Written elsewhere';
        add_filter(self::TITLE_SHORT_CIRCUIT, $plugin);

        try {
            $this->assertNull($this->wordPress()->titles());
        } finally {
            remove_filter(self::TITLE_SHORT_CIRCUIT, $plugin);
        }
    }

    #[Test]
    public function wordpress_alone_prints_no_canonical_on_an_archive(): void
    {
        $this->assertNull($this->onAnArchive(fn (): ?array => $this->wordPress()->canonicals()));
    }

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
