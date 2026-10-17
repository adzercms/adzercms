<?php

declare(strict_types=1);

namespace AdzerCMS\Payments;

use AdzerCMS\Contracts\Ui\ContentSource;
use Symfony\Component\HttpFoundation\Request;

final readonly class PaymentSources implements ContentSource
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function sources(Request $request): array
    {
        return [self::FORM => $this->payments->origins()];
    }
}
