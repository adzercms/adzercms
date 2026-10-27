<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Secrets
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key()));
    }

    public function decrypt(string $cipher): string
    {
        $raw = base64_decode($cipher, true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('ciphertext is damaged');
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key(),
        );

        return false === $plain ? throw new \RuntimeException('ciphertext is not decrypted by the instance key') : $plain;
    }

    public function sign(string $subject): string
    {
        return self::signature($subject, $this->secret());
    }

    public static function signature(string $subject, string $secret): string
    {
        return hash_hmac('sha256', $subject, $secret);
    }

    private function key(): string
    {
        return hash('sha256', $this->secret(), true);
    }

    private function secret(): string
    {
        return '' === $this->secret ? throw new \LogicException('instance key APP_SECRET is not set') : $this->secret;
    }
}
