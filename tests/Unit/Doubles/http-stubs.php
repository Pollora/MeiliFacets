<?php

declare(strict_types=1);

// The HTTP classes the module type-hints, without the packages that ship them: this suite needs nothing but the module.
// Loaded from a test that runs in its own process only.

namespace Symfony\Component\HttpFoundation {
    if (! class_exists(Response::class)) {
        class ResponseHeaderBag
        {
            /** @var array<string, string> */
            public array $all = [];

            public function set(string $key, string $value): void
            {
                $this->all[$key] = $value;
            }

            public function remove(string $key): void
            {
                unset($this->all[$key]);
            }
        }

        class Response
        {
            public ResponseHeaderBag $headers;

            public int $status = 200;

            public function __construct()
            {
                $this->headers = new ResponseHeaderBag;
            }

            public function setStatusCode(int $code): static
            {
                $this->status = $code;

                return $this;
            }
        }
    }
}

namespace Illuminate\Http {
    if (! class_exists(Request::class)) {
        class Request {}
    }
}

namespace Illuminate\Foundation\Http {
    if (! class_exists(Kernel::class)) {
        class Kernel implements \Illuminate\Contracts\Http\Kernel
        {
            /** @var list<string> */
            public array $pushed = [];

            public function pushMiddleware(string $middleware): static
            {
                $this->pushed[] = $middleware;

                return $this;
            }

            public function bootstrap(): void {}

            public function handle($request): mixed
            {
                return null;
            }

            public function terminate($request, $response): void {}

            public function getApplication(): mixed
            {
                return null;
            }
        }
    }
}

namespace Nwidart\Modules\Support {
    use Illuminate\Support\ServiceProvider;

    if (! class_exists(ModuleServiceProvider::class)) {
        /** Only what the module's own provider reads of its parent: its name, and the commands it declares. */
        abstract class ModuleServiceProvider extends ServiceProvider
        {
            protected string $name = '';

            protected string $nameLower = '';

            /** @var list<class-string> */
            protected array $commands = [];

            public function register(): void {}

            public function boot(): void {}
        }
    }
}
