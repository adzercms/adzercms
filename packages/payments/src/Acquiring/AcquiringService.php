<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatableInterface;

#[AutoconfigureTag]
interface AcquiringService extends Owner
{
    public function ready(): bool;

    public function floor(): string;

    /** @return list<string> */
    public function origins(): array;

    /** @return array<string, TranslatableInterface> */
    public function methods(): array;

    /** @return array<string, string> */
    public function fees(): array;

    public function saves(): bool;

    public function binds(): bool;

    /** @throws PaymentFailed */
    public function start(Payment $payment, Addresses $addresses): PaymentOffer;

    /** @throws PaymentFailed */
    public function bind(Payment $binding, Addresses $addresses): PaymentOffer;

    /** @throws PaymentFailed */
    public function state(Payment $payment): ?PaymentCallback;

    /** @throws PaymentFailed */
    public function callback(Request $request): ?PaymentCallback;

    public function acknowledgement(): Response;

    /** @throws PaymentFailed */
    public function charge(Payment $payment, string $token, Addresses $addresses): PaymentStatus;

    /** @throws PaymentFailed */
    public function forget(string $payer, string $token): void;

    /** @throws PaymentFailed */
    public function close(Payment $payment, Closing $closing): void;
}
