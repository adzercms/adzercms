<?php

declare(strict_types=1);

namespace AdzerCMS\BankTransfer;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Money;
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

use function Symfony\Component\Translation\t;

final readonly class BankTransferService implements AcquiringService
{
    public const CARD = 'payment.banktransfer.card';
    public const PHONE = 'payment.banktransfer.phone';
    public const BANK = 'payment.banktransfer.bank';
    public const NOTE = 'payment.banktransfer.note';
    public const METHOD = 'transfer';

    private const PAGE = '@BankTransfer/transfer.html.twig';

    public function __construct(private Settings $settings)
    {
    }

    public static function comment(Payment $payment): string
    {
        return $payment->getPayer() ?? $payment->getDescription();
    }

    public function settingFields(): array
    {
        return [
            new Field(self::CARD, t('bank_transfer.card')),
            new Field(self::PHONE, t('bank_transfer.phone')),
            new Field(self::BANK, t('bank_transfer.bank')),
            new Field(self::NOTE, t('bank_transfer.note')),
        ];
    }

    public function ready(): bool
    {
        return '' !== $this->detail(self::CARD) || '' !== $this->detail(self::PHONE);
    }

    public function floor(): string
    {
        return Money::fromMinorUnits(1);
    }

    public function origins(): array
    {
        return [];
    }

    public function methods(): array
    {
        return [self::METHOD => t('bank_transfer.method')];
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
        if (!$this->ready()) {
            throw new PaymentFailed('the transfer details are not set');
        }

        return PaymentOffer::page(self::PAGE, [
            'card' => $this->detail(self::CARD),
            'phone' => $this->detail(self::PHONE),
            'bank' => $this->detail(self::BANK),
            'note' => $this->detail(self::NOTE),
            'comment' => self::comment($payment),
        ]);
    }

    public function bind(Payment $binding, Addresses $addresses): PaymentOffer
    {
        throw new PaymentFailed('a transfer saves no card');
    }

    public function state(Payment $payment): ?PaymentCallback
    {
        return null;
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
        throw new PaymentFailed('a transfer saves no card');
    }

    public function forget(string $payer, string $token): void
    {
        throw new PaymentFailed('a transfer saves no card');
    }

    public function close(Payment $payment, Closing $closing): void
    {
        throw new PaymentFailed('a transfer gives no receipt');
    }

    private function detail(string $name): string
    {
        return trim($this->settings->get($name));
    }
}
