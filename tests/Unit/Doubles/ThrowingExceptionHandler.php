<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Illuminate\Contracts\Debug\ExceptionHandler;
use RuntimeException;
use Throwable;

final class ThrowingExceptionHandler implements ExceptionHandler
{
    public function report(Throwable $e): void
    {
        throw new RuntimeException('The handler fails as well.');
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function render($request, Throwable $e): never
    {
        throw $e;
    }

    public function renderForConsole($output, Throwable $e): void {}
}
