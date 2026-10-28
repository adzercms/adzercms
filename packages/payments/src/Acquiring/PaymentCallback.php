<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

use AdzerCMS\Payments\PaymentStatus;

final readonly class PaymentCallback
{
    public function __construct(
        public string $number,
        public PaymentStatus $status,
        public ?string $operationId = null,
        public ?string $amount = null,
        public ?BoundCard $card = null,
        public ?string $cardPrint = null,
        public ?string $fee = null,
    ) {
    }
}
