<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Login;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Exclude]
final readonly class Flight
{
    private const SECRET_BYTES = 32;

    private function __construct(public string $owner, public string $state, public string $secret)
    {
    }

    public static function open(SessionInterface $session, string $key, string $owner): self
    {
        $flight = new self($owner, self::token(), self::token());
        $session->set($key, [$flight->owner, $flight->state, $flight->secret]);

        return $flight;
    }

    public static function held(SessionInterface $session, string $key): ?self
    {
        return self::of($session->get($key));
    }

    public static function land(SessionInterface $session, string $key, string $owner, string $state): ?self
    {
        $flight = self::of($session->remove($key));

        return null !== $flight && $owner === $flight->owner && hash_equals($flight->state, $state) ? $flight : null;
    }

    public function challenge(): string
    {
        return self::encode(hash('sha256', $this->secret, true));
    }

    private static function of(mixed $kept): ?self
    {
        if (!\is_array($kept) || 3 !== \count($kept)) {
            return null;
        }
        [$owner, $state, $secret] = array_map(strval(...), array_values($kept));

        return '' === $state ? null : new self($owner, $state, $secret);
    }

    private static function token(): string
    {
        return self::encode(random_bytes(self::SECRET_BYTES));
    }

    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
