<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

final readonly class Callback
{
    /** @param array<string, string> $parameters */
    public function __construct(
        public string $code,
        public string $state,
        public string $secret,
        public string $redirect,
        private array $parameters,
    ) {
    }

    public function parameter(string $name): string
    {
        return $this->parameters[$name] ?? '';
    }
}
