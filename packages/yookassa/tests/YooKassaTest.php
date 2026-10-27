<?php

declare(strict_types=1);

namespace AdzerCMS\YooKassa\Tests;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Payments\Acquiring\Closing;
use AdzerCMS\Payments\Acquiring\PaymentOffer;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentConfirming;
use AdzerCMS\Payments\PaymentService;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Payments\Repository\PaymentRepository;
use AdzerCMS\YooKassa\YooKassaService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class YooKassaTest extends SchemaTestCase
{
    use Configuration;

    private const SENDER = '185.71.76.1';
    private const BACK = 'https://village.example.org/balance';
    private const EMAIL = 'buyer@example.org';

    /** @var list<array{method: string, url: string, body: array<string, mixed>, headers: array<string, string>}> */
    private array $sent = [];

    /** @var list<array<string, mixed>> */
    private array $answers = [];

    /** @var list<PaymentConfirming> */
    private array $confirming = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([
            YooKassaService::SHOP_ID => 'shop',
            YooKassaService::SECRET_KEY => 'shop-secret',
            YooKassaService::TAXATION => '2',
            YooKassaService::VAT => '1',
            YooKassaService::PAYMENT_MODE => 'full_prepayment',
            YooKassaService::PAYMENT_SUBJECT => 'service',
            YooKassaService::CLOSING_SUBJECT => 'service',
        ]);
        self::service(HttpAnswers::class)->respond(function (string $method, string $url, array $options): JsonMockResponse {
            $headers = [];
            foreach ($options['headers'] ?? [] as $header) {
                [$name, $value] = explode(': ', (string) $header, 2) + [1 => ''];
                $headers[mb_strtolower($name)] = $value;
            }
            $body = json_decode((string) ($options['body'] ?? ''), true);
            $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => \is_array($body) ? $body : []];

            return new JsonMockResponse(array_shift($this->answers) ?? []);
        });
        self::service(EventDispatcherInterface::class, 'event_dispatcher')->addListener(PaymentConfirming::class, function (PaymentConfirming $event): void {
            $this->confirming[] = $event;
        });
    }

    #[Group('ACMS-PAY-0018')]
    #[Group('ACMS-PAY-0019')]
    #[Group('ACMS-PAY-0035')]
    #[Group('ACMS-PAY-0036')]
    #[Group('ACMS-CORE-1004')]
    public function testPageOrderGoesToProviderWithReceiptAndIdempotence(): void
    {
        $this->answers = [['id' => 'op-1', 'status' => 'pending', 'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/checkout/op-1']]];
        $payment = $this->order('500.00');

        self::assertSame('https://yoomoney.ru/checkout/op-1', $this->offer($payment)->url);

        $sent = $this->sent[0];
        self::assertSame(['POST', 'https://api.yookassa.ru/v3/payments'], [$sent['method'], $sent['url']]);
        self::assertSame('op-1', $payment->getOperationId());
        self::assertSame($payment->getNumber(), $sent['headers']['idempotence-key'] ?? null, 'the registration repeats with the same key');
        self::assertSame(['value' => '500.00', 'currency' => 'RUB'], $sent['body']['amount']);
        self::assertTrue($sent['body']['capture']);
        self::assertSame(['number' => $payment->getNumber()], $sent['body']['metadata']);
        self::assertArrayNotHasKey('save_payment_method', $sent['body']);
        self::assertSame([
            'customer' => ['email' => self::EMAIL, 'phone' => '79990000000'],
            'tax_system_code' => 2,
            'items' => [[
                'description' => 'Hosting',
                'quantity' => '2.00',
                'amount' => ['value' => '250.00', 'currency' => 'RUB'],
                'vat_code' => 1,
                'payment_mode' => 'full_prepayment',
                'payment_subject' => 'service',
            ]],
        ], $sent['body']['receipt']);
        self::assertTrue($payment->isAdvance());
    }

    #[Group('ACMS-PAY-0035')]
    public function testSbpOrderComesAsCode(): void
    {
        $this->answers = [['id' => 'op-2', 'status' => 'pending', 'confirmation' => ['type' => 'qr', 'confirmation_data' => 'https://qr.nspk.ru/AS1']]];

        $offer = $this->offer($this->order('500.00', YooKassaService::METHOD_SBP));

        self::assertSame('https://qr.nspk.ru/AS1', $offer->qr);
        self::assertSame(['type' => 'sbp'], $this->sent[0]['body']['payment_method_data']);
        self::assertSame(['type' => 'qr'], $this->sent[0]['body']['confirmation']);
    }

    #[Group('ACMS-PAY-0035')]
    public function testRefusedOrderNamesTheReasonOfTheProvider(): void
    {
        self::service(LocaleAwareInterface::class, 'translator')->setLocale('ru');
        $this->answers = [['type' => 'error', 'code' => 'invalid_request', 'description' => 'Amount is too small']];

        $refusal = self::service(PaymentService::class)->start($this->order('1.00'));

        self::assertInstanceOf(Refusal::class, $refusal);
        self::assertStringContainsString('Amount is too small', self::phrase($refusal->reason));
    }

    #[Group('ACMS-PAY-0021')]
    #[Group('ACMS-PAY-0037')]
    public function testClosingReceiptOffsetsThePrepaymentOfThePayment(): void
    {
        $this->answers = [['id' => 'rc-1', 'type' => 'payment', 'payment_id' => 'op-16', 'status' => 'pending']];
        $payment = $this->registered('500.00', 'op-16')->markAdvance(true);

        self::assertNull(self::service(PaymentService::class)->close($payment, new Closing('320.50', 'Hosting of sites', null, self::EMAIL, 'payer-1-2026-10')));

        $sent = $this->sent[0];
        $amount = ['value' => '320.50', 'currency' => 'RUB'];
        self::assertSame('https://api.yookassa.ru/v3/receipts', $sent['url']);
        self::assertSame('payer-1-2026-10', $sent['headers']['idempotence-key'] ?? null);
        self::assertSame(['payment', 'op-16', true], [$sent['body']['type'], $sent['body']['payment_id'], $sent['body']['send']]);
        self::assertSame(['email' => self::EMAIL], $sent['body']['customer']);
        self::assertSame([['description' => 'Hosting of sites', 'quantity' => '1', 'amount' => $amount, 'vat_code' => 1, 'payment_mode' => 'full_payment', 'payment_subject' => 'service']], $sent['body']['items']);
        self::assertSame([['type' => 'prepayment', 'amount' => $amount]], $sent['body']['settlements']);

        $this->answers = [['id' => 'rc-2', 'type' => 'payment', 'payment_id' => 'op-16', 'status' => 'canceled']];
        self::assertInstanceOf(Refusal::class, self::service(PaymentService::class)->close($payment, new Closing('100.00', 'Hosting of sites', null, self::EMAIL, 'payer-1-2026-11')), 'the cancelled receipt is a refusal');
    }

    #[Group('ACMS-PAY-0038')]
    public function testCallbackOfStrangerIsRefusedBeforeTheProvider(): void
    {
        $payment = $this->registered('500.00', 'op-4');

        $this->notify('op-4', '203.0.113.9');

        self::assertResponseStatusCodeSame(400);
        self::assertSame([], $this->sent);
        $payment = $this->reread($payment);
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
    }

    #[Group('ACMS-PAY-0009')]
    #[Group('ACMS-PAY-0017')]
    #[Group('ACMS-PAY-0038')]
    #[Group('ACMS-PAY-0041')]
    public function testCallbackTakesStateFromTheProviderItself(): void
    {
        $payment = $this->registered('500.00', 'op-5');
        $this->answers = [['id' => 'op-5', 'status' => 'succeeded', 'amount' => ['value' => '500.00', 'currency' => 'RUB'],
            'metadata' => ['number' => $payment->getNumber()], 'payment_method' => self::method('pm-5', false, 'RU')]];

        $this->notify('op-5', self::SENDER);

        self::assertResponseIsSuccessful();
        self::assertSame('', $this->client->getResponse()->getContent());
        self::assertSame(['GET', 'https://api.yookassa.ru/v3/payments/op-5'], [$this->sent[0]['method'], $this->sent[0]['url']], 'the state is read again at the provider');
        $payment = $this->reread($payment);
        self::assertSame(PaymentStatus::Confirmed, $payment->getStatus());
        self::assertCount(1, $this->confirming);
        self::assertNull($this->confirming[0]->card, 'the card of a plain payment is not saved');
        self::assertSame(hash('sha256', '2202204242122027'), $this->confirming[0]->cardPrint, 'the card of the payment gives the print');
    }

    #[Group('ACMS-PAY-0014')]
    #[Group('ACMS-PAY-0039')]
    public function testSavedMethodOfRememberingPaymentComesAsCard(): void
    {
        $this->answers = [['id' => 'op-9', 'status' => 'pending', 'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/checkout/op-9']]];
        $payment = $this->order('500.00', remember: true);
        $this->offer($payment);
        self::assertTrue($this->sent[0]['body']['save_payment_method']);
        $this->answers = [['id' => 'op-9', 'status' => 'succeeded', 'amount' => ['value' => '500.00'], 'metadata' => ['number' => $payment->getNumber()], 'payment_method' => self::method('pm-9', true, 'RU')]];

        $this->notify('op-9', self::SENDER);

        $card = $this->confirming[0]->card ?? self::fail('the saved method came without a card');
        self::assertSame(['pm-9', '220220******4242', '12/27', 'RU'], [$card->id, $card->label, $card->expires, $card->issuer]);
    }

    #[Group('ACMS-PAY-0016')]
    #[Group('ACMS-PAY-0039')]
    public function testSavedMethodChargesWithoutThePayerAndForgetsWithoutTheProvider(): void
    {
        $this->answers = [['id' => 'op-6', 'status' => 'succeeded']];
        $service = self::service(PaymentService::class);

        $payment = $service->charge([['name' => 'Hosting', 'quantity' => 1, 'price' => '300.00']], 'Hosting', null, self::EMAIL, 'payer-1', 'pm-1');

        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame(PaymentStatus::Confirmed, $payment->getStatus());
        self::assertSame('pm-1', $this->sent[0]['body']['payment_method_id']);
        self::assertCount(1, $this->confirming);

        $this->sent = [];
        self::assertNull($service->forget('payer-1', 'pm-1'));
        self::assertSame([], $this->sent, 'the provider keeps no list of methods');
    }

    #[Group('ACMS-PAY-0015')]
    #[Group('ACMS-PAY-0040')]
    #[Group('ACMS-PAY-0041')]
    public function testBindingSavesCardWithoutChargeByActiveMethod(): void
    {
        $this->answers = [['id' => 'pm-7', 'status' => 'pending', 'saved' => false, 'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/bind/pm-7']]];
        $service = self::service(PaymentService::class);

        $offer = $service->bind('payer-1', null, self::EMAIL, self::BACK);

        self::assertInstanceOf(PaymentOffer::class, $offer);
        self::assertSame('https://yoomoney.ru/bind/pm-7', $offer->url);
        self::assertSame('https://api.yookassa.ru/v3/payment_methods', $this->sent[0]['url']);
        self::assertSame('bank_card', $this->sent[0]['body']['type']);
        self::assertArrayNotHasKey('amount', $this->sent[0]['body']);
        $binding = self::service(PaymentRepository::class)->findByOperation('pm-7') ?? self::fail('the binding is not kept');
        self::assertTrue($binding->isBinding());

        $this->answers = [self::method('pm-7', true, 'KZ')];
        $this->notify('pm-7', self::SENDER, 'payment_method.active');

        self::assertSame('https://api.yookassa.ru/v3/payment_methods/pm-7', $this->sent[1]['url'], 'the method is read again at the provider');
        $binding = $this->reread($binding);
        self::assertSame(PaymentStatus::Confirmed, $binding->getStatus());
        self::assertSame('0.00', $binding->getAmount(), 'the binding moves no money');
        $card = $this->confirming[0]->card ?? self::fail('the binding came without a card');
        self::assertSame(['pm-7', 'KZ'], [$card->id, $card->issuer]);
        self::assertSame(hash('sha256', '2202204242122027'), $this->confirming[0]->cardPrint);
    }

    #[Group('ACMS-PAY-0040')]
    public function testInactiveMethodRejectsBinding(): void
    {
        $this->answers = [['id' => 'pm-8', 'status' => 'pending', 'saved' => false, 'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/bind/pm-8']]];
        self::service(PaymentService::class)->bind('payer-1', null, self::EMAIL, self::BACK);
        $binding = self::service(PaymentRepository::class)->findByOperation('pm-8') ?? self::fail('the binding is not kept');

        $this->answers = [['id' => 'pm-8', 'type' => 'bank_card', 'status' => 'inactive', 'saved' => false]];
        self::service(PaymentService::class)->refresh($binding);

        self::assertSame(PaymentStatus::Rejected, $binding->getStatus());
        self::assertSame([], $this->confirming);
    }

    /** @return array<string, mixed> */
    private static function method(string $id, bool $saved, string $country): array
    {
        return ['id' => $id, 'type' => 'bank_card', 'status' => $saved ? 'active' : 'pending', 'saved' => $saved,
            'card' => ['first6' => '220220', 'last4' => '4242', 'expiry_month' => '12', 'expiry_year' => '2027', 'card_type' => 'Mir', 'issuer_country' => $country]];
    }

    private function order(string $amount, string $method = YooKassaService::METHOD_PAGE, bool $remember = false): Payment
    {
        $payment = self::service(PaymentService::class)->order(
            [['name' => 'Hosting', 'quantity' => 2, 'price' => bcdiv($amount, '2', 2)]],
            $method,
            'Hosting',
            '79990000000',
            self::EMAIL,
            self::BACK,
            'payer-1',
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

    private function notify(string $operation, string $address, string $event = 'payment.succeeded'): void
    {
        $this->client->request('POST', '/payment/callback', server: ['REMOTE_ADDR' => $address, 'CONTENT_TYPE' => 'application/json'], content: json_encode([
            'type' => 'notification',
            'event' => $event,
            'object' => ['id' => $operation],
        ], \JSON_THROW_ON_ERROR));
    }
}
