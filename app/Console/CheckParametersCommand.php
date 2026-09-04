<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Console;

use Illuminate\Console\Command;
use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\UrlParameters;

final class CheckParametersCommand extends Command
{
    protected $signature = 'meilifacets:check-parameters';

    protected $description = 'Check that no listing URL parameter collides with a reserved name';

    public function handle(UrlParameters $parameters, ReservedParameters $reserved): int
    {
        $conflicts = $reserved->conflicts($parameters->all());

        if ($conflicts === []) {
            $this->info('No conflict. '.count($parameters->all()).' parameters checked.');

            return self::SUCCESS;
        }

        foreach ($conflicts as $parameter => $reason) {
            $this->error("\"{$parameter}\" is {$reason}.");
        }

        $this->line('Rename them in config/meilifacets.php, under url_parameters or query_parameters.');

        return self::FAILURE;
    }
}
