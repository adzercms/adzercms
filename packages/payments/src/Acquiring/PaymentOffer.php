<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

final readonly class PaymentOffer
{
    /** @param array<string, mixed> $context */
    private function __construct(public ?string $url, public ?string $qr, public ?string $template = null, public array $context = [])
    {
    }

    public static function redirect(string $url): self
    {
        return new self($url, null);
    }

    public static function qr(string $payload): self
    {
        return new self(null, $payload);
    }

    /** @param array<string, mixed> $context */
    public static function page(string $template, array $context): self
    {
        return new self(null, null, $template, $context);
    }
}
