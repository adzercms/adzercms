<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Composer;

final readonly class Source
{
    public function __construct(public string $url, private ?string $login = null, private ?string $token = null)
    {
    }

    public static function origin(string $url): string
    {
        $port = parse_url($url, \PHP_URL_PORT);

        return strtolower((string) parse_url($url, \PHP_URL_HOST)).(\is_int($port) ? ':'.$port : '');
    }

    /** @return array<string, mixed> */
    public function options(string $url): array
    {
        return match (true) {
            null === $this->token, self::origin($url) !== self::origin($this->url) => [],
            null === $this->login => ['auth_bearer' => $this->token],
            default => ['auth_basic' => [$this->login, $this->token]],
        };
    }

    /** @return array<string, array<string, mixed>> */
    public function auth(): array
    {
        return match (true) {
            null === $this->token => [],
            null === $this->login => ['bearer' => [self::origin($this->url) => $this->token]],
            default => ['http-basic' => [self::origin($this->url) => ['username' => $this->login, 'password' => $this->token]]],
        };
    }
}
