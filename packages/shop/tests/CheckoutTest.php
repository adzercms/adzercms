<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Tests;

use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Shop\Entity\Order;
use AdzerCMS\Shop\Entity\Product;
use AdzerCMS\Shop\OrderStatus;
use AdzerCMS\Shop\PickupPoint;
use AdzerCMS\Shop\Receipt;
use AdzerCMS\Shop\ShopSettings;
use AdzerCMS\Shop\Tests\Fixture\Counters;
use AdzerCMS\Shop\Tests\Fixture\Till;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Translation\t;

final class CheckoutTest extends ShopTestCase
{
    use Configuration;

    private const CART = '/shop/cart';
    private const ORDER = '/shop/order';

    private Product $mug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mug = $this->product($this->category('Кружки'), 'Кружка', '450', ['250 мл' => '450.50']);
        self::counters()->points = [new PickupPoint('center', 'Ленина, 1', '10:00–20:00')];
        $this->client->setServerParameter('REMOTE_ADDR', '192.0.2.'.random_int(1, 254));
    }

    #[Group('ACMS-SHOP-0015')]
    #[Group('ACMS-SHOP-0016')]
    #[Group('ACMS-SHOP-0019')]
    #[Group('ACMS-US-0042')]
    public function testVisitorTakesOrderFromPointAndPaysOnReceipt(): void
    {
        $this->configure([ShopSettings::PICKUP => '1', ShopSettings::ON_RECEIPT => '1']);
        $this->fill(2);

        $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '8 (999) 123-45-67', 'email' => 'Anna@Example.test', 'comment' => 'Позвонить']);

        $order = $this->em->getRepository(Order::class)->findOneBy([]) ?? self::fail('the order is taken');
        self::assertResponseRedirects(self::ORDER.'/'.$order->getKey(), 303);
        $page = $this->client->followRedirect();
        self::assertSame(self::phrase('shop.flash.ordered'), self::toast($page, 'success'));
        self::assertSame(self::phrase('shop.order.title', ['number' => $order->getId()]), $page->filter('h1')->text());
        self::assertSame([['Кружка250 мл', self::sum('450.50'), '2', self::sum('901')]], $page->filter('tbody tr')->each(static fn (Crawler $row): array => $row->filter('td')->each(static fn (Crawler $cell): string => $cell->text())));
        self::assertSame(['Ленина, 1', self::phrase('shop.payment.receipt')], $page->filter('.info-value')->each(static fn (Crawler $value): string => $value->text()));

        self::assertSame(
            [OrderStatus::New, '79991234567', 'anna@example.test', Receipt::Pickup, 'center', 'Ленина, 1', null, null, '901.00', 'Позвонить'],
            [$order->getStatus(), $order->getPhone(), $order->getEmail(), $order->getReceipt(), $order->getPointKey(), $order->getPointAddress(), $order->getAddress(), $order->getDeliveryPrice(), $order->getTotal(), $order->getComment()],
        );
        self::assertSame(self::phrase('shop.cart.empty'), $this->arrive(self::CART)->filter('.empty-state')->text());
    }

    #[Group('ACMS-SHOP-0012')]
    #[Group('ACMS-US-0042')]
    public function testDeliveryNeedsAddressAndAddsItsPrice(): void
    {
        $this->configure([ShopSettings::DELIVERY => '1', ShopSettings::DELIVERY_PRICE => '300', ShopSettings::ON_RECEIPT => '1']);
        $this->fill(1);

        $page = $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(self::phrase('shop.refusal.address', ['limit' => Order::ADDRESS]), $page->filter('.field-refusal')->text());
        self::assertSame('Анна', $page->filter('input[name="name"]')->attr('value'));
        self::assertSame(self::sum('750.50'), $page->filter('tfoot tr')->last()->filter('th')->eq(1)->text());

        $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567', 'address' => 'Мира, 5, кв. 7']);
        $order = $this->em->getRepository(Order::class)->findOneBy([]) ?? self::fail('the order is taken');
        self::assertSame([Receipt::Delivery, 'Мира, 5, кв. 7', '300.00', '750.50'], [$order->getReceipt(), $order->getAddress(), $order->getDeliveryPrice(), $order->getTotal()]);
    }

    /** @param array<string, string> $fields */
    #[Group('ACMS-SHOP-0013')]
    #[Group('ACMS-SHOP-0015')]
    #[Group('ACMS-US-0042')]
    #[DataProvider('refusedFields')]
    public function testRefusalStandsBesideItsField(array $fields, string $field, string $reason): void
    {
        self::counters()->points[] = new PickupPoint('north', 'Северная, 9');
        $this->configure([ShopSettings::PICKUP => '1', ShopSettings::ON_RECEIPT => '1']);
        $this->fill(1);

        $page = $this->post(self::ORDER, $fields + ['name' => 'Анна', 'phone' => '89991234567', 'point' => 'north']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([self::phrase($reason, ['limit' => Order::NAME])], $page->filter('.field-refusal')->each(static fn (Crawler $refusal): string => $refusal->text()));
        $input = $page->filter(\sprintf('[name="%s"]', $field))->first();
        self::assertCount(1, ($input->closest('fieldset') ?? $input->closest('label') ?? self::fail('the field stands in a label'))->filter('.field-refusal'));
        self::assertSame(0, $this->em->getRepository(Order::class)->count());
    }

    /** @return iterable<string, array{array<string, string>, string, string}> */
    public static function refusedFields(): iterable
    {
        yield 'without name' => [['name' => ' '], 'name', 'shop.refusal.buyer'];
        yield 'unknown phone' => [['phone' => '12'], 'phone', 'shop.refusal.phone'];
        yield 'unknown email' => [['email' => 'anna@'], 'email', 'shop.refusal.email'];
        yield 'point outside' => [['point' => 'south'], 'point', 'shop.refusal.point'];
    }

    #[Group('ACMS-SHOP-0014')]
    #[Group('ACMS-US-0042')]
    public function testShopWithoutWaysNamesReason(): void
    {
        $this->fill(1);
        self::assertSame(self::phrase('shop.refusal.no_receipt'), $this->arrive(self::CART)->filter('.flash-warning')->text());

        $this->configure([ShopSettings::PICKUP => '1']);
        self::assertSame(self::phrase('shop.refusal.no_payment'), $this->arrive(self::CART)->filter('.flash-warning')->text());

        $page = $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(self::phrase('shop.refusal.no_payment'), $page->filter('.flash-warning')->text());
        self::assertSame(0, $this->em->getRepository(Order::class)->count());
    }

    #[Group('ACMS-SHOP-0015')]
    #[Group('ACMS-US-0042')]
    public function testTrapLooksTakenAndFrequentOrdersAreRefused(): void
    {
        $this->configure([ShopSettings::PICKUP => '1', ShopSettings::ON_RECEIPT => '1']);
        $this->fill(1);
        $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567', 'website' => 'spam']);
        self::assertResponseRedirects('/shop', 303);
        self::assertSame(self::phrase('shop.flash.ordered'), self::toast($this->client->followRedirect(), 'success'));
        self::assertSame(0, $this->em->getRepository(Order::class)->count());

        foreach (range(1, 5) as $ignored) {
            $this->fill(1);
            $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567']);
        }
        $this->fill(1);
        $page = $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567']);
        self::assertSame(self::phrase('shop.refusal.often'), $page->filter('.flash-error')->text());
        self::assertSame(5, $this->em->getRepository(Order::class)->count());
    }

    #[Group('ACMS-SHOP-0019')]
    public function testOrderPageOpensOnlyByItsKey(): void
    {
        $this->client->request('GET', self::ORDER.'/'.str_repeat('a', Order::KEY));

        self::assertResponseStatusCodeSame(404);
    }

    #[Group('ACMS-SHOP-0017')]
    #[Group('ACMS-SHOP-0018')]
    #[Group('ACMS-US-0042')]
    public function testVisitorPaysOnlineRightAfterOrder(): void
    {
        $till = self::till();
        $till->ready = true;
        $this->configure([ShopSettings::DELIVERY => '1', ShopSettings::DELIVERY_PRICE => '300', ShopSettings::ONLINE => '1', ShopSettings::ON_RECEIPT => '1']);
        $this->fill(2);

        $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567', 'email' => 'anna@example.test', 'address' => 'Мира, 5', 'payment' => 'online.sbp']);

        $order = $this->em->getRepository(Order::class)->findOneBy([]) ?? self::fail('the order is taken');
        $payment = $this->em->find(Payment::class, $order->getPaymentId()) ?? self::fail('the order is billed');
        $page = 'http://localhost'.self::ORDER.'/'.$order->getKey();
        self::assertResponseRedirects('/payment/'.$payment->getNumber(), 303);
        $this->client->followRedirect();
        self::assertResponseRedirects(Till::PAGE.$payment->getNumber());
        self::assertSame(
            ['sbp', [['name' => 'Кружка, 250 мл', 'quantity' => 2, 'price' => '450.50'], ['name' => self::phrase('shop.cart.delivery'), 'quantity' => 1, 'price' => '300.00']], '1201.00', self::phrase('shop.order.title', ['number' => $order->getId()]), '79991234567', 'anna@example.test', $page],
            [$payment->getMethod(), $payment->getItems(), $payment->getAmount(), $payment->getDescription(), $payment->getPhone(), $payment->getEmail(), $payment->getBack()],
        );
        self::assertSame([OrderStatus::AwaitingPayment, false], [$order->getStatus(), $order->isPaid()]);

        $till->outcome = PaymentStatus::Confirmed;
        $this->client->request('GET', '/payment/'.$payment->getNumber().'/return');
        self::assertResponseRedirects($page);
        $shown = $this->client->followRedirect();

        self::assertSame(['Мира, 5', self::phrase('shop.payment.online').', '.self::phrase('shop.order.paid')], $shown->filter('.info-value')->each(static fn (Crawler $value): string => $value->text()));
        self::assertCount(0, $shown->filter('form[action$="/pay"]'));
        $order = $this->reread($order);
        self::assertSame([OrderStatus::New, true], [$order->getStatus(), $order->isPaid()]);
    }

    #[Group('ACMS-SHOP-0019')]
    #[Group('ACMS-US-0042')]
    #[DataProvider('unsettled')]
    public function testUnpaidOrderPaysFromItsPage(PaymentStatus $outcome, bool $same): void
    {
        $till = self::till();
        $till->ready = true;
        $this->configure([ShopSettings::PICKUP => '1', ShopSettings::ONLINE => '1']);
        $this->fill(1);
        $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567', 'payment' => 'online.card']);
        $order = $this->em->getRepository(Order::class)->findOneBy([]) ?? self::fail('the order is taken');
        $first = $this->em->find(Payment::class, $order->getPaymentId()) ?? self::fail('the order is billed');

        $till->outcome = $outcome;
        $this->client->request('GET', '/payment/'.$first->getNumber().'/return');
        $this->client->submit($this->client->followRedirect()->filter('form[action$="/pay"]')->form());

        $order = $this->reread($order);
        $payment = $this->em->find(Payment::class, $order->getPaymentId()) ?? self::fail('the order is billed');
        self::assertResponseRedirects('/payment/'.$payment->getNumber(), 303);
        self::assertSame([$same, 'card'], [$payment->getNumber() === $first->getNumber(), $payment->getMethod()]);
    }

    /** @return iterable<string, array{PaymentStatus, bool}> */
    public static function unsettled(): iterable
    {
        yield 'pending payment offers itself again' => [PaymentStatus::Pending, true];
        yield 'rejected payment gives way to a new one' => [PaymentStatus::Rejected, false];
    }

    /** @param list<string> $ways */
    #[Group('ACMS-SHOP-0014')]
    #[Group('ACMS-US-0042')]
    #[DataProvider('onlineWays')]
    public function testOnlineWaysNeedReadyAcquiringAndToggle(bool $ready, string $online, array $ways): void
    {
        self::till()->ready = $ready;
        $this->configure([ShopSettings::PICKUP => '1', ShopSettings::ONLINE => $online, ShopSettings::ON_RECEIPT => '1']);
        $this->fill(1);

        $page = $this->arrive(self::CART);

        self::assertSame($ways, $page->filter('input[name="payment"]')->each(static fn (Crawler $way): string => (string) $way->attr('value')));
    }

    /** @return iterable<string, array{bool, string, list<string>}> */
    public static function onlineWays(): iterable
    {
        yield 'ready and on' => [true, '1', ['online.card', 'online.sbp', 'receipt']];
        yield 'not ready' => [false, '1', []];
        yield 'off' => [true, '0', []];
    }

    #[Group('ACMS-SHOP-0014')]
    #[Group('ACMS-US-0042')]
    public function testOnlineBelowFloorIsRefused(): void
    {
        $till = self::till();
        $till->ready = true;
        $till->floor = '1000';
        $this->configure([ShopSettings::PICKUP => '1', ShopSettings::ONLINE => '1']);
        $this->fill(1);

        $page = $this->post(self::ORDER, ['name' => 'Анна', 'phone' => '89991234567', 'payment' => 'online.card']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(self::phrase(t('shop.refusal.floor', ['floor' => Money::precise('1000')])), $page->filter('.field-refusal')->text());
        self::assertSame(0, $this->em->getRepository(Order::class)->count());
    }

    private function fill(int $quantity): void
    {
        $this->post('/shop/cart', ['product' => (string) $this->mug->getId(), 'variant' => (string) $this->mug->getVariants()[0]->getId(), 'quantity' => (string) $quantity]);
        $this->client->followRedirect();
    }

    private static function counters(): Counters
    {
        return self::service(Counters::class);
    }

    private static function till(): Till
    {
        return self::service(Till::class);
    }

    private static function sum(string $amount): string
    {
        return self::phrase(Money::precise($amount));
    }
}
