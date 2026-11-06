<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Closure;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;

/**
 * The robots directives `wp_robots()` prints for a listing's bare view and for any other view, the module's own
 * included, so that the client writes the right ones on the view it turns to.
 *
 * @phpstan-type ByView array{bare: string, secondary: string}
 */
final readonly class ListingRobots
{
    /** Where `IndexingPolicy` filters: before Yoast SEO, which makes its directives agree with a `noindex`. */
    private const int POLICY_PRIORITY = 10;

    /** After a project that answers the listing-page filter itself. */
    private const int LAST = PHP_INT_MAX;

    private const string CONTENT = "/content='([^']*)'/";

    public function __construct(private IndexingPolicy $policy) {}

    /**
     * @return ByView|null
     */
    public function byView(): ?array
    {
        if (has_action('wp_head', 'wp_robots') === false) {
            return null;
        }

        return ['bare' => $this->bare(), 'secondary' => $this->secondary()];
    }

    /** The policy holds back on a page that renders no listing. */
    private function bare(): string
    {
        return $this->printedWith(ListingPage::FILTER, static fn (): bool => false, self::LAST);
    }

    private function secondary(): string
    {
        return $this->printedWith('wp_robots', $this->policy->asSecondaryView(...), self::POLICY_PRIORITY);
    }

    /**
     * @param  Closure(mixed): mixed  $filter
     */
    private function printedWith(string $hook, Closure $filter, int $priority): string
    {
        add_filter($hook, $filter, $priority);

        try {
            return $this->printed();
        } finally {
            remove_filter($hook, $filter, $priority);
        }
    }

    /** `wp_robots()` prints its tag and returns nothing. */
    private function printed(): string
    {
        ob_start();
        wp_robots();
        $tag = (string) ob_get_clean();

        return preg_match(self::CONTENT, $tag, $content) === 1
            ? html_entity_decode($content[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';
    }
}
