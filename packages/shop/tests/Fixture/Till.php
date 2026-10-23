<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Tests\Fixture;

use AdzerCMS\Payments\Acquiring\AcquiringService;
use AdzerCMS\Payments\Acquiring\Addresses;
use AdzerCMS\Payments\Acquiring\Closing;
use AdzerCMS\Payments\Acquiring\PaymentCallback;
use AdzerCMS\Payments\Acquiring\PaymentFailed;
use AdzerCMS\Payments\Acquiring\PaymentOffer;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;

final class Till implements AcquiringService
{
    public const PAGE = 'https://till.example.test/';

    public bool $ready = false;
    public string $floor = '0';
    public PaymentStatus $outcome = PaymentStatus::Pending;

    public function settingFields(): array
    {
        return [];
    }

    public function ready(): bool
    {
        return $this->ready;
    }

    public function floor(): string
    {
        return $this->floor;
    }

    public function origins(): array
    {
        return [];
    }

    public function methods(): array
    {
        return ['card' => new TranslatableMessage('Card'), 'sbp' => new TranslatableMessage('SBP')];
    }

    public function fees(): array
    {
        return [];
    }

    public function saves(): bool
    {
        return false;
    }

    public function binds(): bool
    {
        return false;
    }

    public function start(Payment $payment, Addresses $addresses): PaymentOffer
    {
        return PaymentOffer::redirect(self::PAGE.$payment->getNumber());
    }

    public function bind(Payment $binding, Addresses $addresses): PaymentOffer
    {
        throw new PaymentFailed('the till binds no cards');
    }

    public function state(Payment $payment): PaymentCallback
    {
        return new PaymentCallback($payment->getNumber(), $this->outcome, 'till-'.$payment->getNumber());
    }

    public function callback(Request $request): ?PaymentCallback
    {
        return null;
    }

    public function acknowledgement(): Response
    {
        return new Response();
    }

    public function charge(Payment $payment, string $token, Addresses $addresses): PaymentStatus
    {
        throw new PaymentFailed('the till keeps no cards');
    }

    public function forget(string $payer, string $token): void
    {
        throw new PaymentFailed('the till keeps no cards');
    }

    public function close(Payment $payment, Closing $closing): void
    {
        throw new PaymentFailed('the till takes no advances');
    }
}
