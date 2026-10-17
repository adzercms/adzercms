<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

final readonly class PaymentOffer
{
    private function __construct(public ?string $url, public ?string $qr)
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
}
