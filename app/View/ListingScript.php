<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\BrowserConnection;
use Pollora\Attributes\Filter;

/**
 * Loads the browser client and hands it what it needs. WordPress prints script
 * modules and their data in the footer, so a listing that asks for it while it
 * renders is still in time — and only the listings actually on the page are
 * described.
 */
final class ListingScript
{
    public const string MODULE = '@meilifacets/listing';

    public const string PRIORITY_FILTER = 'meilifacets/script_fetchpriority';

    private const string SOURCE = 'modules/meilifacets/dist/listing.js';

    private const string DEFAULT_PRIORITY = 'low';

    /** @var array<string, array<string, mixed>> */
    private array $described = [];

    public function __construct(
        private readonly BrowserConnection $connection,
        private readonly ListingDescription $description,
    ) {}

    public function require(ResolvedListing $listing): void
    {
        if (! $this->connection->isConfigured() || ! is_file(public_path(self::SOURCE))) {
            return;
        }

        $this->enqueueOnce();

        $this->described[$listing->name()] = $this->description->of($listing);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Filter('script_module_data_'.self::MODULE)]
    public function data(array $data): array
    {
        if ($this->described === []) {
            return $data;
        }

        return [
            ...$data,
            'connection' => [
                'url' => $this->connection->url,
                'key' => $this->connection->key,
                'index' => $this->connection->index,
            ],
            'listings' => $this->described,
        ];
    }

    /**
     * WP Rocket's Delay JS holds back every script it does not exclude until the visitor's first gesture.
     *
     * @param  array<string>  $exclusions
     * @return array<string>
     */
    #[Filter('rocket_delay_js_exclusions')]
    public function excludeFromDelayedScripts(array $exclusions): array
    {
        return [...$exclusions, self::SOURCE];
    }

    private function enqueueOnce(): void
    {
        if ($this->described !== []) {
            return;
        }

        wp_enqueue_script_module(
            self::MODULE,
            asset(self::SOURCE),
            [],
            (string) filemtime(public_path(self::SOURCE)),
            ['fetchpriority' => $this->fetchPriority()],
        );
    }

    private function fetchPriority(): string
    {
        return (string) apply_filters(self::PRIORITY_FILTER, self::DEFAULT_PRIORITY);
    }
}
