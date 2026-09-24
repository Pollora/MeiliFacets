<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Console;

use Illuminate\Console\Command;
use Modules\MeiliFacets\Support\PublishedAssets;

final class CheckAssetsCommand extends Command
{
    protected $signature = 'meilifacets:check-assets';

    protected $description = 'Check that the published assets match the ones the module holds';

    public function handle(PublishedAssets $assets): int
    {
        $stale = $assets->stale();

        if ($stale === []) {
            $this->info('Published assets are up to date.');

            return self::SUCCESS;
        }

        foreach ($stale as $path) {
            $this->error("\"{$path}\" is missing or older than the module's own copy.");
        }

        $this->line('Run '.PublishedAssets::COMMAND.', then clear the page cache.');

        return self::FAILURE;
    }
}
