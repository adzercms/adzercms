<?php

declare(strict_types=1);

namespace AdzerCMS\BankTransfer\Tests;

use AdzerCMS\BankTransfer\BankTransferService;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentConfirmed;
use AdzerCMS\Payments\PaymentConfirming;
use AdzerCMS\Payments\PaymentService;
use AdzerCMS\Payments\PaymentStatus;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class BankTransferTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;

    private const BACK = 'https://village.example.org/balance';
    private const CARD = '2200 0000 0000 0000';
    private const PHONE = '+7 900 000-00-00';
    private const NOTE = 'Payments are taken by transfer for now';

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([BankTransferService::CARD => self::CARD, BankTransferService::PHONE => self::PHONE, BankTransferService::BANK => 'Village bank', BankTransferService::NOTE => self::NOTE]);
        $dispatcher = self::service(EventDispatcherInterface::class, 'event_dispatcher');
        foreach ([PaymentConfirming::class, PaymentConfirmed::class] as $event) {
            $dispatcher->addListener($event, function (object $happened): void {
                $this->events[] = $happened;
            });
        }
    }

    #[Group('ACMS-PAY-0044')]
    public function testTransferIsReadyByCardOrPhoneAndKeepsNoCard(): void
    {
        $service = self::service(PaymentService::class);

        self::assertSame([BankTransferService::METHOD], array_keys($service->methods()));
        self::assertFalse($service->saves());
        self::assertSame([], $service->origins());

        $transfer = self::service(BankTransferService::class);
        self::assertSame(BankTransferService::DEFAULT_DAYS, $transfer->lifetime()->d, 'the default payment term');
        $this->configure([BankTransferService::DAYS => '0']);
        self::assertSame(1, $transfer->lifetime()->d, 'a day at least');

        $this->unconfigure(BankTransferService::CARD);
        self::assertTrue($service->ready(), 'the phone alone is enough');

        $this->unconfigure(BankTransferService::PHONE);
        self::assertFalse($service->ready());
        self::assertSame([], $service->methods());
    }

    #[Group('ACMS-FLOW-0006')]
    #[Group('ACMS-PAY-0006')]
    #[Group('ACMS-PAY-0045')]
    #[Group('ACMS-UI-0002')]
    public function testPageGivesDetailsToCopyWithPayerAsComment(): void
    {
        $payment = $this->order('payer-7');
        $page = $this->client->request('GET', '/payment/'.$payment->getNumber());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(self::NOTE, $page->filter('.pay')->text(), 'the operator note');
        self::assertStringContainsString(self::phrase('bank_transfer.page.until', ['at' => $payment->getExpiresAt()]), $page->filter('.pay')->text(), 'the payment term');
        self::assertSame(
            [self::CARD, self::PHONE, 'payer-7', '250'],
            array_map(static fn (string $key): ?string => $page->filter('#transfer-'.$key)->attr('value'), ['card', 'phone', 'comment', 'amount']),
        );
        self::assertCount(4, $page->filter('.pay [data-copy]'), 'card, phone, comment and amount are copied');
        self::assertCount(0, $page->filter('[hx-get]'), 'the page does not wait for the outcome');
        self::assertSame(self::BACK, $page->filter('.pay-methods a')->attr('href'));

        $this->unconfigure(BankTransferService::PHONE);
        $page = $this->client->request('GET', '/payment/'.$this->order(null)->getNumber());

        self::assertCount(0, $page->filter('#transfer-phone'), 'an empty setting is not shown');
        self::assertSame('Hosting', $page->filter('#transfer-comment')->attr('value'), 'the description without a payer');

        $this->client->loginUser($this->record('79990000003'));
        $page = $this->client->request('GET', '/payment/'.$payment->getNumber());

        self::assertCount(1, $page->filter('.panel-header details.menu'), 'a signed-in payer keeps the frame of the cabinet');
    }

    #[Group('ACMS-PAY-0043')]
    #[Group('ACMS-PAY-0046')]
    #[Group('ACMS-US-0066')]
    public function testOperatorConfirmsAwaitingAndCancelledTransferByReceivedAmountOnce(): void
    {
        self::mockTime('2026-08-10 10:00');
        $late = $this->order('payer-1');
        self::mockTime('2026-08-12 10:00');
        self::command('adzercms:payments')->execute([]);
        $awaiting = $this->order('payer-2');
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();

        $list = $this->client->request('GET', '/admin/transfers');
        self::assertSame(['payer-2'], self::comments($list));
        self::assertSame(['/admin/transfers/'.$awaiting->getNumber()], $list->filter('.row-actions button')->each(static fn (Crawler $button): ?string => $button->attr('hx-get')), 'the row confirms by its button');
        self::assertSame(['payer-1'], self::comments($this->client->request('GET', '/admin/transfers?status=cancelled')));

        $this->post('/admin/transfers/'.$late->getNumber(), ['amount' => '0']);
        self::assertResponseStatusCodeSame(422);

        $this->post('/admin/transfers/'.$late->getNumber(), ['amount' => '300']);
        self::assertResponseStatusCodeSame(204);
        $late = $this->reread($late);
        self::assertSame([PaymentStatus::Confirmed, '300.00'], [$late->getStatus(), $late->getAmount()], 'the cancelled payment is confirmed by the received amount');
        self::assertSame([PaymentConfirming::class, PaymentConfirmed::class], array_map(get_class(...), $this->events));

        $this->post('/admin/transfers/'.$late->getNumber(), ['amount' => '300']);
        self::assertResponseStatusCodeSame(404);
        self::assertCount(2, $this->events, 'the repeat credits nothing');
        self::assertSame(PaymentStatus::Pending, $this->reread($awaiting)->getStatus());
    }

    #[Group('ACMS-US-0066')]
    public function testTransfersSortByPayerOrDescriptionInItsPlace(): void
    {
        $this->order('I-payer');
        $this->order(null);
        $this->order('A-payer');
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();

        self::assertSame(['A-payer', 'Hosting', 'I-payer'], self::comments($this->client->request('GET', '/admin/transfers?sort=comment&order=asc')),
            'a transfer without payer sorts by the description shown in its place');
    }

    private function order(?string $payer): Payment
    {
        $payment = self::service(PaymentService::class)->order(
            [['name' => 'Hosting', 'quantity' => 1, 'price' => '250.00']],
            BankTransferService::METHOD,
            'Hosting',
            '79990000000',
            null,
            self::BACK,
            $payer,
        );

        return $payment instanceof Payment ? $payment : self::fail(self::phrase($payment->reason));
    }

    /** @return list<string> */
    private static function comments(Crawler $listing): array
    {
        return self::firstCells($listing);
    }
}
