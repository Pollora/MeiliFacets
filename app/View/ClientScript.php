<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Closure;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Search\BrowserConnection;
use Pollora\Attributes\Filter;

/**
 * Loads a browser bundle and hands it what it needs. WordPress prints script
 * modules and their data in the footer, so a root that asks for it while it
 * renders is still in time — and only the roots actually on the page are
 * described.
 */
final class ClientScript
{
    public const string PRIORITY_FILTER = 'meilifacets/script_fetchpriority';

    private const string DEFAULT_PRIORITY = 'low';

    private const string DATA_FILTER_PREFIX = 'script_module_data_';

    /** @var array<string, array<string, array<string, mixed>>> descriptions by module, then by root name */
    private array $described = [];

    public function __construct(private readonly BrowserConnection $connection) {}

    /**
     * @param  Closure(): array<string, mixed>  $description  read only when the bundle is published
     */
    public function require(ScriptModule $module, string $root, Closure $description): void
    {
        if (! $this->canLoad($module)) {
            return;
        }

        $this->enqueueOnce($module);

        $this->described[$module->value][$root] = $description();
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
        return [...$exclusions, ...$this->sources()];
    }

    /**
     * @return list<string>
     */
    private function sources(): array
    {
        $published = array_filter(ScriptModule::cases(), $this->isPublished(...));

        return array_values(array_map(static fn (ScriptModule $module): string => $module->source(), $published));
    }

    private function canLoad(ScriptModule $module): bool
    {
        return $this->connection->isConfigured() && $this->isPublished($module);
    }

    private function isPublished(ScriptModule $module): bool
    {
        return is_file(public_path($module->source()));
    }

    private function enqueueOnce(ScriptModule $module): void
    {
        if (isset($this->described[$module->value])) {
            return;
        }

        wp_enqueue_script_module(
            $module->value,
            asset($module->source()),
            [],
            (string) filemtime(public_path($module->source())),
            ['fetchpriority' => $this->fetchPriority($module)],
        );

        // WordPress names the data filter after the module: no attribute can declare it for every bundle.
        add_filter(self::DATA_FILTER_PREFIX.$module->value, fn (array $data): array => $this->data($module, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function data(ScriptModule $module, array $data): array
    {
        return [
            ...$data,
            'connection' => [
                'url' => $this->connection->url,
                'key' => $this->connection->key,
                'index' => $this->connection->index,
            ],
            $module->roots() => $this->described[$module->value],
        ];
    }

    private function fetchPriority(ScriptModule $module): string
    {
        return (string) apply_filters(self::PRIORITY_FILTER, self::DEFAULT_PRIORITY, $module->value);
    }
}
