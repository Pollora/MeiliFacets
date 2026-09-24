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
        $accepted = array_intersect_key($conflicts, array_flip($reserved->acceptedFor($parameters)));
        $blocking = array_diff_key($conflicts, $accepted);

        foreach ($blocking as $parameter => $reason) {
            $this->error("\"{$parameter}\" is {$reason}.");
        }

        if ($blocking !== []) {
            $this->line('Rename them in config/meilifacets.php, under url_parameters or query_parameters.');

            return self::FAILURE;
        }

        foreach ($accepted as $parameter => $reason) {
            $this->warn("\"{$parameter}\" is {$reason}.");
        }

        if ($accepted !== []) {
            $this->line('Taken on purpose: the bounds keep the names WooCommerce reads, so a link written');
            $this->line('for it drives the module. Rename them under query_parameters to give that up.');
        }

        $this->info('No blocking conflict. '.count($parameters->all()).' parameters checked.');

        return self::SUCCESS;
    }
}
