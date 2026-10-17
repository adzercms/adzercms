<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

final readonly class Authorization
{
    /** @param array<string, string> $parameters */
    public function __construct(
        public string $url,
        public string $client,
        public string $scope,
        public bool $pkce = true,
        public bool $nonce = false,
        public array $parameters = [],
    ) {
    }
}
