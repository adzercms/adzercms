<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Tests;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentService;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Payments\Repository\PaymentRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class PaymentTest extends SchemaTestCase
{
    private const BACK = 'https://village.example.org/balance';

    #[Group('ACMS-PAY-0005')]
    public function testWithoutAcquiringModuleThereIsNoWayToPay(): void
    {
        $service = self::service(PaymentService::class);

        self::assertFalse($service->ready());
        self::assertSame([], $service->methods());
        $refusal = $service->order([['name' => 'Hosting', 'quantity' => 1, 'price' => '100.00']], 'page', 'Hosting', '79990000000', null, self::BACK);

        self::assertInstanceOf(Refusal::class, $refusal);
        self::assertSame(self::phrase('payments.refusal.method'), self::phrase($refusal->reason));
        self::assertSame([], self::service(PaymentRepository::class)->findAll());
    }

    #[Group('ACMS-PAY-0013')]
    public function testRoundCancelsOnlyStaleUnpaidPayment(): void
    {
        self::mockTime('2026-08-10 10:00');
        $stale = $this->payment();
        $paid = $this->payment()->confirm('100.00', 'operation-1');
        $this->em->flush();
        self::mockTime('2026-08-12 10:00');
        $fresh = $this->payment();

        $run = self::command('adzercms:payments');

        self::assertSame(Command::SUCCESS, $run->execute([]));
        $this->em->clear();
        $payments = self::service(PaymentRepository::class);
        self::assertSame(PaymentStatus::Cancelled, $payments->findByNumber($stale->getNumber())?->getStatus());
        self::assertSame(PaymentStatus::Confirmed, $payments->findByNumber($paid->getNumber())?->getStatus());
        self::assertSame(PaymentStatus::Pending, $payments->findByNumber($fresh->getNumber())?->getStatus());
    }

    #[Group('ACMS-PAY-0042')]
    public function testPaymentPagesAreAddressedByNumberWithoutSignIn(): void
    {
        $payment = $this->payment()->confirm('100.00', 'operation-1');
        $this->em->flush();

        $this->client->request('GET', '/payment/'.$payment->getNumber().'/return');
        self::assertResponseRedirects(self::BACK);

        $this->client->request('GET', '/payment/'.$payment->getNumber());
        self::assertResponseRedirects(self::BACK, message: 'the settled payment leads back');

        $this->client->request('GET', '/payment/'.str_repeat('0', 32).'/return');
        self::assertResponseStatusCodeSame(404);
    }

    private function payment(): Payment
    {
        $payment = new Payment([['name' => 'Hosting', 'quantity' => 1, 'price' => '100.00']], '100.00', 'page', 'Hosting', '79990000000', null, null, self::BACK, new \DateInterval('P1D'));
        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }
}
