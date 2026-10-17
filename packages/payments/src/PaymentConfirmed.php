<?php

declare(strict_types=1);

namespace AdzerCMS\Payments;

use AdzerCMS\Payments\Entity\Payment;

final readonly class PaymentConfirmed
{
    public function __construct(public Payment $payment)
    {
    }
}
