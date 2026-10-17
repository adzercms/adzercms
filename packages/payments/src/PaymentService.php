<?php

declare(strict_types=1);

namespace AdzerCMS\Payments;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Payments\Acquiring\AcquiringService;
use AdzerCMS\Payments\Acquiring\Addresses;
use AdzerCMS\Payments\Acquiring\Closing;
use AdzerCMS\Payments\Acquiring\PaymentCallback;
use AdzerCMS\Payments\Acquiring\PaymentFailed;
use AdzerCMS\Payments\Acquiring\PaymentOffer;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\Repository\PaymentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

/** @phpstan-import-type Item from Payment */
final class PaymentService
{
    private const LIFETIME = '-1 day';

    private readonly ?AcquiringService $acquiring;

    /** @param iterable<AcquiringService> $modules */
    public function __construct(
        #[AutowireIterator(AcquiringService::class)]
        iterable $modules,
        private readonly PaymentRepository $payments,
        private readonly EventDispatcherInterface $events,
        private readonly UrlGeneratorInterface $urls,
        private readonly EntityManagerInterface $em,
    ) {
        $this->acquiring = [...$modules][0] ?? null;
    }

    public function ready(): bool
    {
        return true === $this->acquiring?->ready();
    }

    /** @return array<string, TranslatableInterface> */
    public function methods(): array
    {
        return $this->ready() ? $this->module()->methods() : [];
    }

    public function floor(): string
    {
        return $this->ready() ? Money::normalize($this->module()->floor()) : Money::normalize('0');
    }

    /** @return array<string, string> */
    public function fees(): array
    {
        return $this->ready() ? array_filter(array_map(trim(...), $this->module()->fees()), static fn (string $fee): bool => '' !== $fee) : [];
    }

    /** @return list<string> */
    public function origins(): array
    {
        return $this->ready() ? $this->module()->origins() : [];
    }

    public function saves(): bool
    {
        return $this->ready() && $this->module()->saves();
    }

    public function binds(): bool
    {
        return $this->saves() && $this->module()->binds();
    }

    /** @param list<Item> $items */
    public function order(array $items, string $method, string $description, ?string $phone, ?string $email, string $back, ?string $payer = null, bool $remember = false): Payment|Refusal
    {
        if (!isset($this->methods()[$method])) {
            return new Refusal(t('payments.refusal.method'));
        }

        $refusal = $this->contacts($phone, $email) ?? $this->above($amount = self::sum($items));
        if (null !== $refusal) {
            return $refusal;
        }

        $payment = new Payment($items, $amount, $method, $description, ContactKind::Phone->normalize($phone), ContactKind::Email->normalize($email), $payer, $back);
        if ($remember && $this->saves()) {
            $payment->remember();
        }
        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    public function start(Payment $payment): PaymentOffer|Refusal
    {
        $offer = $payment->offer();
        if (null !== $offer) {
            return $offer;
        }

        try {
            $offer = $this->module()->start($payment, $this->addresses($payment));
        } catch (PaymentFailed $failure) {
            $this->em->flush();

            return self::failed($failure);
        }
        $payment->keep($offer);
        $this->em->flush();

        return $offer;
    }

    public function bind(string $payer, ?string $phone, ?string $email, string $back): PaymentOffer|Refusal
    {
        if (!$this->binds()) {
            return new Refusal(t('payments.refusal.binding'));
        }

        $binding = new Payment([], Money::normalize('0'), Payment::BINDING, '', ContactKind::Phone->normalize($phone), ContactKind::Email->normalize($email), $payer, $back);
        $binding->remember();
        $this->em->persist($binding);
        $this->em->flush();

        try {
            $offer = $this->module()->bind($binding, $this->addresses($binding));
        } catch (PaymentFailed $failure) {
            return self::failed($failure);
        }
        $binding->keep($offer);
        $this->em->flush();

        return $offer;
    }

    /** @param list<Item> $items */
    public function charge(array $items, string $description, ?string $phone, ?string $email, string $payer, string $token): Payment|Refusal
    {
        if (!$this->saves()) {
            return new Refusal(t('payments.refusal.saving'));
        }

        $refusal = $this->contacts($phone, $email) ?? $this->above($amount = self::sum($items));
        if (null !== $refusal) {
            return $refusal;
        }

        $payment = new Payment($items, $amount, Payment::SAVED, $description, ContactKind::Phone->normalize($phone), ContactKind::Email->normalize($email), $payer, '');
        $this->em->persist($payment);
        $this->em->flush();

        try {
            $status = $this->module()->charge($payment, $token, $this->addresses($payment));
        } catch (PaymentFailed) {
            $status = PaymentStatus::Rejected;
        }

        if (PaymentStatus::Confirmed !== $status) {
            $payment->setStatus(PaymentStatus::Rejected);
            $this->em->flush();

            return $payment;
        }

        $this->em->flush();
        $this->settle($payment, new PaymentCallback($payment->getNumber(), $status, $payment->getOperationId()));

        return $payment;
    }

    public function forget(string $payer, string $token): ?Refusal
    {
        try {
            $this->module()->forget($payer, $token);
        } catch (PaymentFailed $failure) {
            return self::failed($failure);
        }

        return null;
    }

    public function close(Payment $payment, Closing $closing): ?Refusal
    {
        if (!$payment->isAdvance()) {
            return new Refusal(t('payments.refusal.closing'));
        }

        $refusal = $this->contacts($closing->phone, $closing->email);
        if (null !== $refusal) {
            return $refusal;
        }

        try {
            $this->module()->close($payment, $closing);
        } catch (PaymentFailed $failure) {
            return self::failed($failure);
        }

        return null;
    }

    /** @throws PaymentFailed */
    public function refresh(Payment $payment): void
    {
        if (PaymentStatus::Pending !== $payment->getStatus() || !$this->ready()) {
            return;
        }

        $state = $this->module()->state($payment);

        if (PaymentStatus::Confirmed === $state?->status) {
            $this->settle($payment, $state);

            return;
        }

        if (null !== $state && PaymentStatus::Pending !== $state->status) {
            $payment->setStatus($state->status);
            $this->em->flush();
        }
    }

    /** @throws PaymentFailed */
    public function callback(Request $request): bool
    {
        $callback = $this->module()->callback($request);
        if (null === $callback) {
            return false;
        }

        $payment = '' !== $callback->number
            ? $this->payments->findByNumber($callback->number)
            : (null !== $callback->operationId ? $this->payments->findByOperation($callback->operationId) : null);

        if (null === $payment) {
            return true;
        }

        if (PaymentStatus::Rejected === $callback->status && PaymentStatus::Pending === $payment->getStatus()) {
            $payment->setStatus(PaymentStatus::Rejected)->setOperationId($callback->operationId ?? $payment->getOperationId());
            $this->em->flush();

            return true;
        }

        if (PaymentStatus::Confirmed === $callback->status) {
            $this->settle($payment, $callback);
        }

        return true;
    }

    public function acknowledgement(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->module()->acknowledgement();
    }

    public function cancelStale(): int
    {
        return $this->payments->cancelStale(now(self::LIFETIME));
    }

    /** @return array{settled: list<string>, failures: list<string>} */
    public function settleStarted(): array
    {
        $settled = [];
        $failures = [];

        foreach ($this->payments->started(now(self::LIFETIME)) as $payment) {
            try {
                $this->refresh($payment);
            } catch (PaymentFailed $failure) {
                $failures[] = $failure->getMessage();

                continue;
            }

            if (PaymentStatus::Confirmed === $payment->getStatus()) {
                $settled[] = $payment->getNumber();
            }
        }

        return ['settled' => $settled, 'failures' => array_values(array_unique($failures))];
    }

    private function settle(Payment $payment, PaymentCallback $callback): void
    {
        $confirmed = $this->em->wrapInTransaction(function () use ($payment, $callback): ?Payment {
            $locked = $this->em->find(Payment::class, $payment->getId(), LockMode::PESSIMISTIC_WRITE);
            if (null === $locked || PaymentStatus::Confirmed === $locked->getStatus()) {
                return null;
            }

            $taken = null !== $callback->operationId ? $this->payments->findByOperation($callback->operationId) : null;
            if (null !== $taken && $taken->getId() !== $locked->getId()) {
                return null;
            }

            $locked->confirm(null !== $callback->amount ? Money::normalize($callback->amount) : $locked->getAmount(), $callback->operationId);
            $this->events->dispatch(new PaymentConfirming($locked, $locked->remembers() ? $callback->card : null, $callback->cardPrint));
            $this->em->flush();

            return $locked;
        });

        if (null !== $confirmed) {
            $this->events->dispatch(new PaymentConfirmed($confirmed));
        }
    }

    private function addresses(Payment $payment): Addresses
    {
        return new Addresses(
            $this->urls->generate('payment_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->urls->generate('payment_return', ['number' => $payment->getNumber()], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    private function contacts(?string $phone, ?string $email): ?Refusal
    {
        return null === ContactKind::Phone->normalize($phone) && null === ContactKind::Email->normalize($email)
            ? new Refusal(t('payments.refusal.contact'))
            : null;
    }

    private function above(string $amount): ?Refusal
    {
        return Money::compare($amount, $this->floor()) < 0
            ? new Refusal(t('payments.refusal.floor', ['floor' => Money::rubles($this->floor())]))
            : null;
    }

    /** @param list<Item> $items */
    private static function sum(array $items): string
    {
        $amount = Money::normalize('0');
        foreach ($items as $item) {
            $amount = Money::add($amount, Money::multiply($item['price'], $item['quantity']));
        }

        return $amount;
    }

    private static function failed(PaymentFailed $failure): Refusal
    {
        return new Refusal(t('payments.refusal.provider', ['reason' => $failure->getMessage()]));
    }

    private function module(): AcquiringService
    {
        return $this->acquiring ?? throw new PaymentFailed('no acquiring module is selected');
    }
}
