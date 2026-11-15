<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Tests;

use AdzerCMS\Shop\Controller\Admin\OrderController;
use AdzerCMS\Shop\Entity\Order;
use AdzerCMS\Shop\Orders;
use AdzerCMS\Shop\OrderSlice;
use AdzerCMS\Shop\OrderStatus;
use AdzerCMS\Shop\PaymentMethod;
use AdzerCMS\Shop\Receipt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class OrderAdminTest extends ShopTestCase
{
    private const LIST = '/admin/shop/orders';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInOwner();
    }

    #[Group('ACMS-SHOP-0028')]
    #[Group('ACMS-US-0043')]
    public function testTabsSplitOrdersByStatusWithCountsAndMenuCountsNewOnes(): void
    {
        $this->order('Анна', PaymentMethod::OnReceipt);
        $this->order('Борис', PaymentMethod::OnReceipt);
        $this->order('Вера', PaymentMethod::OnReceipt, OrderStatus::InProgress);
        $this->order('Глеб', PaymentMethod::Online);
        $this->order('Дина', PaymentMethod::OnReceipt, OrderStatus::Done);
        $this->order('Егор', PaymentMethod::OnReceipt, OrderStatus::Cancelled);

        $page = $this->arrive(self::LIST);

        self::assertSame('2', trim($page->filter('a.nav-item[href="'.self::LIST.'"] .badge')->text()));
        self::assertSame(
            [self::phrase('shop.slice.new', ['count' => 2]), self::phrase('shop.slice.progress', ['count' => 1]), self::phrase('shop.slice.awaiting', ['count' => 1]), self::phrase('shop.slice.closed', ['count' => 2])],
            $page->filter('nav.tabs a[hx-select]')->each(static fn (Crawler $tab): string => trim($tab->text())),
        );
        self::assertEqualsCanonicalizing(['Анна', 'Борис'], self::names($page));
        self::assertEqualsCanonicalizing(['Дина', 'Егор'], self::names($this->fragment(self::LIST.'?'.Orders::SLICE.'='.OrderSlice::Closed->value)));
    }

    #[Group('ACMS-US-0043')]
    #[DataProvider('terms')]
    public function testSearchFindsOrderByNumberNameAndContacts(string $term, string $found): void
    {
        $this->order('Анна', PaymentMethod::OnReceipt, email: 'anna@site.test');
        $boris = $this->order('Борис', PaymentMethod::OnReceipt, phone: '79990000003');

        $term = '#' === $term ? (string) $boris->getId() : $term;

        self::assertSame([$found], self::names($this->fragment(self::LIST.'?q='.rawurlencode($term))));
    }

    /** @return iterable<string, array{string, string}> */
    public static function terms(): iterable
    {
        yield 'number' => ['#', 'Борис'];
        yield 'name' => ['анн', 'Анна'];
        yield 'phone as typed' => ['+7 (999) 000-00-03', 'Борис'];
        yield 'email' => ['anna@', 'Анна'];
    }

    #[Group('ACMS-SHOP-0027')]
    #[Group('ACMS-US-0043')]
    public function testOrderPageShowsBuyerAndChangesStatusButNeverToAwaitingPayment(): void
    {
        $order = $this->order('Анна', PaymentMethod::Online, email: 'anna@site.test');
        $page = '/admin/shop/orders/'.$order->getId();

        $shown = $this->arrive($page);
        self::assertSame(['tel:+79991234567', 'mailto:anna@site.test'], $shown->filter('a.info-value')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        self::assertSame(
            ['', ...array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::MANUAL)],
            $shown->filter('select[name="'.OrderController::STATUS.'"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value')),
        );

        $refused = $this->post($page, [OrderController::STATUS => OrderStatus::AwaitingPayment->value]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(self::phrase('shop.refusal.status'), $refused->filter('.flash-error')->text());

        $this->post($page, [OrderController::STATUS => OrderStatus::InProgress->value]);
        self::assertResponseRedirects($page, 303);
        self::assertSame(self::phrase('shop.flash.staged', ['number' => $order->getId()]), self::toast($this->client->followRedirect(), 'success'));
        self::assertSame(OrderStatus::InProgress, $this->reread($order)->getStatus());
    }

    private function order(string $name, PaymentMethod $payment, ?OrderStatus $status = null, string $phone = '79991234567', ?string $email = null): Order
    {
        $order = new Order($name, $phone, $email, Receipt::Pickup, 'center', 'Ленина, 1', null, null, $payment, null, '450.00');
        $order->hold(null, 'Кружка', null, '450.00', 1);
        if (null !== $status) {
            $order->stage($status);
        }
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    /** @return list<string> */
    private static function names(Crawler $listing): array
    {
        return self::firstCells($listing, '.clip');
    }
}
