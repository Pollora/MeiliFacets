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
