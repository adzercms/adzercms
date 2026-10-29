<?php

declare(strict_types=1);

namespace AdzerCMS\TBank\Tests;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Payments\Acquiring\Closing;
use AdzerCMS\Payments\Acquiring\PaymentOffer;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentConfirmed;
use AdzerCMS\Payments\PaymentConfirming;
use AdzerCMS\Payments\PaymentService;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Payments\Repository\PaymentRepository;
use AdzerCMS\TBank\TBankService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

use function Symfony\Component\Translation\t;

final class TBankTest extends SchemaTestCase
{
    use Configuration;

    private const TERMINAL = 'terminal';
    private const SECRET = 'terminal-secret';
    private const BACK = 'https://village.example.org/balance';
    private const PHONE = '79990000000';

    /** @var array<string, array<string, mixed>> */
    private array $requests = [];

    /** @var array<string, string> */
    private array $urls = [];

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([
            TBankService::API_URL => 'https://securepay.tinkoff.ru/v2',
            TBankService::TERMINAL_KEY => self::TERMINAL,
            TBankService::TERMINAL_PASSWORD => self::SECRET,
            TBankService::TAXATION => 'usn_income',
            TBankService::VAT => 'none',
            TBankService::PAYMENT_METHOD => 'full_prepayment',
            TBankService::PAYMENT_OBJECT => 'service',
            TBankService::CLOSING_OBJECT => 'service',
        ]);
        $dispatcher = self::service(EventDispatcherInterface::class, 'event_dispatcher');
        foreach ([PaymentConfirming::class, PaymentConfirmed::class] as $event) {
            $dispatcher->addListener($event, function (object $happened): void {
                $this->events[] = $happened;
            });
        }
    }

    #[Group('ACMS-PAY-0001')]
    #[Group('ACMS-PAY-0007')]
    #[Group('ACMS-PAY-0018')]
    #[Group('ACMS-PAY-0019')]
    #[Group('ACMS-PAY-0025')]
    #[Group('ACMS-PAY-0030')]
    #[Group('ACMS-PAY-0032')]
    public function testStartRegistersSignedOrderWithReceiptAndGivesBankPage(): void
    {
        $this->respond(['Init' => ['Success' => true, 'PaymentId' => '900001', 'PaymentURL' => 'https://pay.tbank.ru/form']]);
        $payment = $this->order('250.00', payer: 'payer-1');

        $offer = $this->offer($payment);

        self::assertSame('https://pay.tbank.ru/form', $offer->url);
        self::assertSame('900001', $payment->getOperationId());
        self::assertTrue($payment->isAdvance(), 'the receipt goes by the payment method of the setting: full prepayment is an advance');

        $sent = $this->sent('Init');
        $urls = self::service(UrlGeneratorInterface::class);
        self::assertSame(self::TERMINAL, $sent['TerminalKey']);
        self::assertSame(25000, $sent['Amount'], 'the amount goes in kopecks');
        self::assertSame($payment->getNumber(), $sent['OrderId']);
        self::assertSame('payer-1', $sent['CustomerKey']);
        self::assertArrayNotHasKey('Recurrent', $sent);
        self::assertSame($urls->generate('payment_callback', [], UrlGeneratorInterface::ABSOLUTE_URL), $sent['NotificationURL']);
        self::assertSame($urls->generate('payment_return', ['number' => $payment->getNumber()], UrlGeneratorInterface::ABSOLUTE_URL), $sent['SuccessURL']);
        self::assertSame($sent['SuccessURL'], $sent['FailURL']);
        self::assertSame(self::sign(array_filter($sent, static fn (mixed $value, string $key): bool => !\is_array($value) && 'Token' !== $key, \ARRAY_FILTER_USE_BOTH)), $sent['Token'], 'the root scalars are signed, the receipt is not');
        self::assertSame([
            'Phone' => '+'.self::PHONE,
            'Taxation' => 'usn_income',
            'Items' => [[
                'Name' => 'Hosting',
                'Price' => 12500,
                'Quantity' => 2,
                'Amount' => 25000,
                'Tax' => 'none',
                'PaymentMethod' => 'full_prepayment',
                'PaymentObject' => 'service',
            ]],
        ], $sent['Receipt']);
    }

    #[Group('ACMS-PAY-0048')]
    public function testLongDescriptionAndItemNameAreCutToTheLimitsOfTheBank(): void
    {
        $this->respond(['Init' => ['Success' => true, 'PaymentId' => '900001', 'PaymentURL' => 'https://pay.tbank.ru/form']]);

        $this->offer($this->order('250.00', name: str_repeat('Ж', 200)));

        $sent = $this->sent('Init');
        self::assertSame(str_repeat('Ж', 140), $sent['Description']);
        self::assertSame(str_repeat('Ж', 128), $sent['Receipt']['Items'][0]['Name']);
    }

    #[Group('ACMS-PAY-0008')]
    public function testRepeatedStartGivesKeptOfferWithoutSecondRegistration(): void
    {
        $this->respond(['Init' => ['Success' => true, 'PaymentId' => '900001', 'PaymentURL' => 'https://pay.tbank.ru/form']]);
        $payment = $this->order('250.00');
        $this->offer($payment);
        $this->requests = [];

        self::assertSame('https://pay.tbank.ru/form', $this->offer($payment)->url);
        self::assertSame([], $this->requests, 'the bank is not asked twice');
    }

    #[Group('ACMS-PAY-0008')]
    public function testRepeatedPageStartWithoutKeptAddressIsRefusal(): void
    {
        $this->respond(['Init' => ['Success' => true, 'PaymentId' => '900001']]);
        $payment = $this->order('250.00');
        $service = self::service(PaymentService::class);

        $first = $service->start($payment);
        self::assertInstanceOf(Refusal::class, $first);
        self::assertSame('900001', $payment->getOperationId());
        $this->requests = [];

        self::assertInstanceOf(Refusal::class, $service->start($payment), 'the registered payment without an address is not registered again');
        self::assertSame([], $this->requests);
    }

    #[Group('ACMS-PAY-0006')]
    #[Group('ACMS-PAY-0028')]
    public function testQrPaymentAsksBankForPayloadOfRegisteredOrder(): void
    {
        $this->respond([
            'Init' => ['Success' => true, 'PaymentId' => '900001'],
            'GetQr' => ['Success' => true, 'Data' => 'https://qr.nspk.ru/AAA'],
        ]);

        $offer = $this->offer($this->order('250.00', 'qr'));

        self::assertSame('https://qr.nspk.ru/AAA', $offer->qr);
        self::assertNull($offer->url);
        self::assertSame('900001', $this->sent('GetQr')['PaymentId']);
        self::assertSame('PAYLOAD', $this->sent('GetQr')['DataType']);
    }

    #[Group('ACMS-PAY-0029')]
    public function testQrRefusalCarriesProviderText(): void
    {
        self::service(LocaleAwareInterface::class, 'translator')->setLocale('ru');
        $this->respond([
            'Init' => ['Success' => true, 'PaymentId' => '900001'],
            'GetQr' => ['Success' => false, 'Details' => 'Amount is out of the SBP range'],
        ]);

        $refusal = self::service(PaymentService::class)->start($this->order('250.00', 'qr'));

        self::assertInstanceOf(Refusal::class, $refusal);
        self::assertStringContainsString('Amount is out of the SBP range', self::phrase($refusal->reason));
    }

    #[Group('ACMS-PAY-0002')]
    #[Group('ACMS-PAY-0003')]
    #[Group('ACMS-PAY-0020')]
    public function testOrderOutsideProviderRulesIsRefusedBeforeTheBank(): void
    {
        $this->respond([]);
        $service = self::service(PaymentService::class);
        $items = [['name' => 'Hosting', 'quantity' => 1, 'price' => '100.00']];

        foreach ([
            'payments.refusal.method' => $service->order($items, 'cash', 'Hosting', self::PHONE, null, self::BACK),
            'payments.refusal.contact' => $service->order($items, 'page', 'Hosting', null, null, self::BACK),
            'payments.refusal.floor' => $service->order([['name' => 'Hosting', 'quantity' => 1, 'price' => '9.99']], 'page', 'Hosting', self::PHONE, null, self::BACK),
        ] as $reason => $refusal) {
            self::assertInstanceOf(Refusal::class, $refusal, $reason);
            self::assertSame(self::phrase($reason, ['floor' => Money::rubles('10')]), self::phrase($refusal->reason));
        }

        self::assertSame([], $this->requests);
        self::assertSame([], self::service(PaymentRepository::class)->findAll(), 'no payment is kept');
        self::assertSame(['qr', 'page'], array_keys($service->methods()));
    }

    #[Group('ACMS-PAY-0009')]
    #[Group('ACMS-PAY-0010')]
    #[Group('ACMS-PAY-0011')]
    #[Group('ACMS-PAY-0034')]
    public function testSignedCallbackConfirmsOnceByProviderAmountAndAnswersOk(): void
    {
        $this->respond([]);
        $payment = $this->registered('500.00', '900001');

        $this->notify($payment, 'CONFIRMED', '300.00');

        self::assertResponseIsSuccessful();
        self::assertSame('OK', $this->client->getResponse()->getContent(), 'the bank repeats the callback on any other body');
        $payment = $this->reread($payment);
        self::assertSame(PaymentStatus::Confirmed, $payment->getStatus());
        self::assertSame('300.00', $payment->getAmount(), 'the amount confirmed by the provider wins');
        self::assertNotNull($payment->getConfirmedAt());
        self::assertSame([PaymentConfirming::class, PaymentConfirmed::class], array_map(get_class(...), $this->events));

        $this->notify($payment, 'CONFIRMED');
        $this->notify($payment, 'REJECTED');

        self::assertSame('OK', $this->client->getResponse()->getContent());
        self::assertCount(2, $this->events, 'the repeat confirms nothing');
        $payment = $this->reread($payment);
        self::assertSame(PaymentStatus::Confirmed, $payment->getStatus(), 'a late refusal does not cancel the confirmed payment');
    }

    #[Group('ACMS-PAY-0009')]
    public function testCallbackWithBadSignatureIsRejected(): void
    {
        $this->respond([]);
        $payment = $this->registered('500.00', '900001');

        $this->notify($payment, 'CONFIRMED', tamper: true);

        self::assertResponseStatusCodeSame(400);
        $payment = $this->reread($payment);
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
        self::assertSame([], $this->events);
    }

    #[Group('ACMS-PAY-0027')]
    public function testFinalRefusalRejectsAndIntermediateStateKeepsPayment(): void
    {
        $this->respond([]);
        $waiting = $this->registered('500.00', '900001');
        $refused = $this->registered('500.00', '900002');

        $this->notify($waiting, 'AUTHORIZED');
        $this->notify($refused, 'DEADLINE_EXPIRED');

        $waiting = $this->reread($waiting);
        $refused = $this->reread($refused);
        self::assertSame(PaymentStatus::Pending, $waiting->getStatus());
        self::assertSame(PaymentStatus::Rejected, $refused->getStatus());
    }

    #[Group('ACMS-PAY-0010')]
    public function testOperationOfAnotherPaymentIsNotConfirmedTwice(): void
    {
        $this->respond([]);
        $first = $this->registered('500.00', '900001');
        $second = $this->order('500.00');
        $this->notify($first, 'CONFIRMED');

        $this->notify($second, 'CONFIRMED', operation: '900001');

        $second = $this->reread($second);
        self::assertSame(PaymentStatus::Pending, $second->getStatus());
        self::assertCount(2, $this->events);
    }

    #[Group('ACMS-PAY-0011')]
    public function testFailureOfOwnerLeavesPaymentToNextCallback(): void
    {
        $this->respond([]);
        $payment = $this->registered('500.00', '900001');
        $dispatcher = self::service(EventDispatcherInterface::class, 'event_dispatcher');
        $failing = static function (): never {
            throw new \RuntimeException('the owner failed');
        };
        $dispatcher->addListener(PaymentConfirming::class, $failing);
        $this->client->catchExceptions(false);

        try {
            $this->notify($payment, 'CONFIRMED');
            self::fail('the failure of the owner was swallowed');
        } catch (\RuntimeException $failure) {
            self::assertSame('the owner failed', $failure->getMessage());
        }

        self::assertSame(PaymentStatus::Pending->value, $this->em->getConnection()->fetchOne('SELECT status FROM payment WHERE id = ?', [$payment->getId()]), 'the confirmation is rolled back');
    }

    #[Group('ACMS-PAY-0014')]
    #[Group('ACMS-PAY-0017')]
    #[Group('ACMS-PAY-0030')]
    #[Group('ACMS-PAY-0031')]
    public function testConfirmedRememberingPaymentGivesItsCard(): void
    {
        $this->respond(['Init' => ['Success' => true, 'PaymentId' => '900001', 'PaymentURL' => 'https://pay.tbank.ru/form']]);
        $remembering = $this->order('500.00', payer: 'payer-1', remember: true);
        $this->offer($remembering);
        $plain = $this->registered('300.00', '900002');

        self::assertSame('Y', $this->sent('Init')['Recurrent']);

        $this->notify($remembering, 'CONFIRMED', card: '77');
        $this->notify($plain, 'CONFIRMED', card: '78');

        $confirming = array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof PaymentConfirming));
        self::assertCount(2, $confirming);
        $card = $confirming[0]->card ?? self::fail('the remembering payment gave no card');
        self::assertSame(['77', '430000******0777', '12/30'], [$card->id, $card->label, $card->expires]);
        self::assertSame(hash('sha256', '4300000777122030'), $confirming[0]->cardPrint, 'the mask and the term give the print');
        self::assertNull($confirming[1]->card, 'the card of a plain payment is not saved');
        self::assertSame(hash('sha256', '4300000777122030'), $confirming[1]->cardPrint);
    }

    #[Group('ACMS-PAY-0016')]
    #[Group('ACMS-PAY-0030')]
    public function testChargeBySavedCardTakesItsRebill(): void
    {
        $this->respond([
            'GetCardList' => [['CardId' => '76', 'Status' => 'A', 'RebillId' => '54'], ['CardId' => '77', 'Status' => 'A', 'RebillId' => '55']],
            'Init' => ['Success' => true, 'PaymentId' => '900003'],
            'Charge' => ['Success' => true, 'Status' => 'CONFIRMED'],
        ]);
        $service = self::service(PaymentService::class);

        $payment = $service->charge([['name' => 'Hosting', 'quantity' => 1, 'price' => '500.00']], 'Hosting', self::PHONE, null, 'payer-1', '77');

        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame(PaymentStatus::Confirmed, $payment->getStatus());
        self::assertSame('payer-1', $this->sent('GetCardList')['CustomerKey']);
        self::assertSame(['900003', '55'], [$this->sent('Charge')['PaymentId'], $this->sent('Charge')['RebillId']]);
        self::assertCount(2, $this->events);

        $this->respond([
            'GetCardList' => [['CardId' => '77', 'Status' => 'A', 'RebillId' => '55']],
            'Init' => ['Success' => true, 'PaymentId' => '900004'],
            'Charge' => ['Success' => false, 'Status' => 'REJECTED'],
        ]);
        $declined = $service->charge([['name' => 'Hosting', 'quantity' => 1, 'price' => '500.00']], 'Hosting', self::PHONE, null, 'payer-1', '77');

        self::assertInstanceOf(Payment::class, $declined);
        self::assertSame(PaymentStatus::Rejected, $declined->getStatus());
        self::assertCount(2, $this->events);
    }

    #[Group('ACMS-PAY-0014')]
    public function testForgottenCardLeavesTheBank(): void
    {
        $this->respond(['RemoveCard' => ['Success' => true]]);

        self::assertNull(self::service(PaymentService::class)->forget('payer-1', '77'));
        self::assertSame(['payer-1', '77'], [$this->sent('RemoveCard')['CustomerKey'], $this->sent('RemoveCard')['CardId']]);
    }

    #[Group('ACMS-PAY-0021')]
    #[Group('ACMS-PAY-0033')]
    public function testClosingReceiptOffsetsTheAdvanceOfThePayment(): void
    {
        $this->respond([TBankService::class => ['Success' => true]]);
        $service = self::service(PaymentService::class);
        $payment = $this->registered('500.00', '900001')->markAdvance(true);

        self::assertNull($service->close($payment, new Closing('320.50', 'Hosting of sites', self::PHONE, null, 'payer-1-2026-10')));

        self::assertSame('https://securepay.tinkoff.ru/cashbox/SendClosingReceipt', $this->urls['SendClosingReceipt']);
        $sent = $this->sent('SendClosingReceipt');
        self::assertSame('900001', $sent['PaymentId']);
        self::assertSame(['Electronic' => 0, 'AdvancePayment' => 32050], $sent['Receipt']['Payments']);
        self::assertSame('full_payment', $sent['Receipt']['Items'][0]['PaymentMethod']);
        self::assertSame(32050, $sent['Receipt']['Items'][0]['Amount']);

        $refusal = $service->close($payment->markAdvance(false), new Closing('320.50', 'Hosting of sites', self::PHONE, null, 'payer-1-2026-11'));
        self::assertInstanceOf(Refusal::class, $refusal);
        self::assertSame(self::phrase('payments.refusal.closing'), self::phrase($refusal->reason));
    }

    #[Group('ACMS-PAY-0012')]
    #[Group('ACMS-PAY-0013')]
    public function testRoundSettlesStartedPaymentAndCancelsStale(): void
    {
        self::mockTime('2026-08-10 10:00');
        $this->respond(['GetState' => ['Success' => true, 'Status' => 'CONFIRMED', 'Amount' => 50000]]);
        $stale = $this->order('500.00');
        self::mockTime('2026-08-12 10:00');
        $started = $this->registered('500.00', '900001');
        $unstarted = $this->order('500.00');

        $run = self::command('adzercms:payments');

        self::assertSame(Command::SUCCESS, $run->execute([]));
        self::assertStringContainsString($started->getNumber(), $run->getDisplay());
        $this->em->clear();
        $payments = self::service(PaymentRepository::class);
        self::assertSame(PaymentStatus::Confirmed, $payments->findByNumber($started->getNumber())?->getStatus());
        self::assertSame(PaymentStatus::Pending, $payments->findByNumber($unstarted->getNumber())?->getStatus(), 'the unregistered payment is not asked about');
        self::assertSame(PaymentStatus::Cancelled, $payments->findByNumber($stale->getNumber())?->getStatus());
        self::assertSame(['GetState'], array_keys($this->requests));
    }

    #[Group('ACMS-CORE-0044')]
    #[Group('ACMS-PAY-0012')]
    public function testUnreachableProviderLeavesPaymentToNextRoundAndFailsTheRound(): void
    {
        $payment = $this->registered('500.00', '900001');
        self::service(HttpAnswers::class)->respond(static function (): never {
            throw new TransportException('the bank is silent');
        });

        $run = self::command('adzercms:payments');

        self::assertSame(Command::FAILURE, $run->execute([]));
        self::assertStringContainsString('the bank is silent', $run->getDisplay());
        $payment = $this->reread($payment);
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
    }

    #[Group('ACMS-PAY-0004')]
    #[Group('ACMS-PAY-0006')]
    #[Group('ACMS-PAY-0026')]
    #[Group('ACMS-PAY-0042')]
    public function testCodePageWaitsForProviderAndLeadsBack(): void
    {
        $this->respond([
            'Init' => ['Success' => true, 'PaymentId' => '900001'],
            'GetQr' => ['Success' => true, 'Data' => 'https://qr.nspk.ru/AAA'],
            'GetState' => ['Success' => true, 'Status' => 'NEW'],
        ]);
        $payment = $this->order('250.00', 'qr');

        $page = $this->client->request('GET', '/payment/'.$payment->getNumber());

        self::assertResponseIsSuccessful();
        self::assertSame('https://qr.nspk.ru/AAA', $page->filter('.pay-methods a')->first()->attr('href'));
        self::assertStringStartsWith('data:image/svg+xml;base64,', (string) $page->filter('img.qr')->attr('src'));
        $policy = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString('https://securepay.tbank.ru', $policy, 'the form leaves for the bank');
        self::assertStringContainsString('https://pay.tbank-online.com', $policy);

        $this->client->request('GET', '/payment/'.$payment->getNumber().'/state');
        self::assertResponseIsSuccessful();
        self::assertSame(self::BACK, $page->filter('.pay-methods a')->last()->attr('href'));

        $this->respond(['GetState' => ['Success' => true, 'Status' => 'CONFIRMED']]);
        $this->client->request('GET', '/payment/'.$payment->getNumber().'/state');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(self::BACK, $this->client->getResponse()->headers->get('HX-Redirect'));

        $this->client->request('GET', '/payment/'.$payment->getNumber());
        self::assertResponseRedirects(self::BACK, message: 'the settled payment leads back');
        $this->client->request('GET', '/payment/'.str_repeat('0', 32));
        self::assertResponseStatusCodeSame(404);
    }

    #[Group('ACMS-PAY-0007')]
    #[Group('ACMS-PAY-0042')]
    public function testReturnAsksStateAndLeadsToOwnerWithOutcome(): void
    {
        $this->respond(['GetState' => ['Success' => true, 'Status' => 'REJECTED']]);
        $payment = $this->registered('500.00', '900001');

        $this->client->request('GET', '/payment/'.$payment->getNumber().'/return');

        self::assertResponseRedirects(self::BACK);
        self::assertSame(['GetState'], array_keys($this->requests));
        $payment = $this->reread($payment);
        self::assertSame(PaymentStatus::Rejected, $payment->getStatus());
        $session = $this->client->getRequest()->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        self::assertEquals([t('payments.flash.rejected')], $session->getFlashBag()->peek('error'));
    }

    /** @param array<string, mixed> $answers */
    private function respond(array $answers): void
    {
        $this->requests = [];
        $this->urls = [];
        self::service(HttpAnswers::class)->respond(function (string $method, string $url, array $options) use ($answers): JsonMockResponse {
            $api = substr($url, strrpos($url, '/') + 1);
            $this->requests[$api] = json_decode((string) $options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->urls[$api] = $url;

            return new JsonMockResponse($answers[$api] ?? $answers[TBankService::class] ?? []);
        });
    }

    /** @return array<string, mixed> */
    private function sent(string $api): array
    {
        return $this->requests[$api] ?? self::fail(\sprintf('the bank was not asked %s', $api));
    }

    private function order(string $amount, string $method = 'page', ?string $payer = null, bool $remember = false, string $name = 'Hosting'): Payment
    {
        $payment = self::service(PaymentService::class)->order(
            [['name' => $name, 'quantity' => 2, 'price' => Money::divide($amount, 2)]],
            $method,
            $name,
            self::PHONE,
            null,
            self::BACK,
            $payer,
            $remember,
        );

        return $payment instanceof Payment ? $payment : self::fail(self::phrase($payment->reason));
    }

    private function registered(string $amount, string $operation): Payment
    {
        $payment = $this->order($amount)->setOperationId($operation);
        $this->em->flush();

        return $payment;
    }

    private function offer(Payment $payment): PaymentOffer
    {
        $offer = self::service(PaymentService::class)->start($payment);

        return $offer instanceof PaymentOffer ? $offer : self::fail(self::phrase($offer->reason));
    }

    private function notify(Payment $payment, string $status, ?string $amount = null, bool $tamper = false, ?string $operation = null, ?string $card = null): void
    {
        $payload = [
            'TerminalKey' => self::TERMINAL,
            'OrderId' => $payment->getNumber(),
            'Success' => 'CONFIRMED' === $status,
            'Status' => $status,
            'PaymentId' => $operation ?? $payment->getOperationId() ?? '900009',
            'Amount' => Money::toMinorUnits($amount ?? $payment->getAmount()),
        ];
        if (null !== $card) {
            $payload += ['CardId' => $card, 'Pan' => '430000******0777', 'ExpDate' => '1230', 'RebillId' => 'R'.$card];
        }
        $payload['Token'] = $tamper ? 'forged' : self::sign($payload);

        $this->client->request('POST', '/payment/callback', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $values */
    private static function sign(array $values): string
    {
        $values['Password'] = self::SECRET;
        ksort($values);

        return hash('sha256', implode('', array_map(static fn (mixed $value): string => \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, $values)));
    }
}
