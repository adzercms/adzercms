<?php

declare(strict_types=1);

namespace AdzerCMS\Payments;

use AdzerCMS\Payments\Acquiring\BoundCard;
use AdzerCMS\Payments\Entity\Payment;

final readonly class PaymentConfirming
{
    public function __construct(
        public Payment $payment,
        public ?BoundCard $card,
        public ?string $cardPrint,
    ) {
    }
}
