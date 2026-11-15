<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Shop\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final readonly class Orders
{
    public const SLICE = 'slice';

    public function __construct(
        private EntityManagerInterface $em,
        private Notifier $notifier,
        private UserRepository $users,
    ) {
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        /** @var list<array{status: OrderStatus, total: int|string}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('o.status AS status', 'COUNT(o.id) AS total')
            ->from(Order::class, 'o')
            ->groupBy('o.status')
            ->getQuery()->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array{table: Table, rows: list<Order>} */
    public function listing(Request $request, string $route): array
    {
        $counts = $this->counts();
        $labels = [];
        foreach (\array_slice(OrderSlice::cases(), 1) as $slice) {
            $labels[$slice->value] = $slice->label($counts);
        }
        $tabs = Filter::tabs(self::SLICE, OrderSlice::Fresh->label($counts), $labels);
        $slice = OrderSlice::tryFrom(Table::values($request, [$tabs])[self::SLICE] ?? '') ?? OrderSlice::Fresh;

        $table = Table::of($request, $route, [
            Column::of('number', t('shop.column.number'), 'o.id'),
            Column::of('phone', t('shop.field.phone'), 'o.phone')->aside(),
            Column::of('total', t('shop.cart.total'), 'o.total'),
            Column::of('receipt', t('shop.field.receipt'), 'o.receipt')->aside(),
            Column::of('payment', t('shop.field.payment'), 'o.payment')->aside(),
            Column::of('arrived', t('shop.column.arrived'), 'o.arrived')->aside(),
            Column::of('status', t('core.column.status'), 'o.status'),
        ], 'arrived', t('shop.orders.empty'), false, filters: [$tabs, Filter::search()]);

        $builder = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', array_map(static fn (OrderStatus $status): string => $status->value, $slice->statuses()));

        $term = trim($table->term());
        if ('' !== $term) {
            $match = Filter::matching($builder, $term, ['o.name', 'o.email']);
            $phone = Filter::phone($builder, $term, 'o.phone');
            if (null !== $phone) {
                $match->add($phone);
            }
            $number = Filter::number($term);
            if (null !== $number) {
                $match->add('o.id = :number');
                $builder->setParameter('number', $number);
            }
            $builder->andWhere($match);
        }

        $table->apply($builder);
        $builder->addOrderBy('o.id', \SortDirection::Descending);

        /** @var list<Order> $rows */
        $rows = $table->paginate($builder->getQuery());

        return ['table' => $table, 'rows' => $rows];
    }

    public function announce(Order $order): void
    {
        $this->notifier->each($this->users->admins(), ShopEvent::NewOrder, ['order' => $order], (string) $order->getId());
    }
}
