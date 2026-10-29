<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final class TableTest extends TestCase
{
    /**
     * @param array<string, string> $query
     * @param list<string>          $expected
     */
    #[Group('ACMS-UI-0081')]
    #[Group('ACMS-UI-0009')]
    #[DataProvider('cuts')]
    public function testLeadRowsStayAboveTheSortedRecordsAndGoThroughTheSearchAndTheFilters(array $query, array $expected): void
    {
        $initial = Filter::select('initial', t('initial'), ['a' => 'a', 'b' => 'b', 'z' => 'z'])->by(static fn (string $row): string => $row[0]);
        $table = Table::of(new Request($query), 'list', [Column::live('name', t('name'), static fn (string $row): string => $row)], 'name',
            filters: [Filter::search(), $initial], live: true);

        self::assertSame($expected, $table->rows(['beta', 'alpha', 'gamma'], ['zeta']));
    }

    /** @return iterable<string, array{array<string, string>, list<string>}> */
    public static function cuts(): iterable
    {
        yield 'ascending' => [[], ['zeta', 'alpha', 'beta', 'gamma']];
        yield 'descending' => [['sort' => 'name', 'order' => 'desc'], ['zeta', 'gamma', 'beta', 'alpha']];
        yield 'search past the lead' => [['q' => 'al'], ['alpha']];
        yield 'search of the lead' => [['q' => 'z'], ['zeta']];
        yield 'filter past the lead' => [['initial' => 'b'], ['beta']];
        yield 'filter of the lead' => [['initial' => 'z'], ['zeta']];
    }

    #[Group('ACMS-UI-0009')]
    #[DataProvider('declarations')]
    public function testLabelledColumnWithoutOrderOfItsListingIsRefusedOutsideTheManualOrder(Column $column, bool $live, ?Order $order, bool $refused): void
    {
        if ($refused) {
            $this->expectException(\LogicException::class);
        }

        $table = Table::of(new Request(), 'list', [$column], $column->key, live: $live, order: $order);

        self::assertSame([$column], $table->columns);
    }

    /** @return iterable<string, array{Column, bool, ?Order, bool}> */
    public static function declarations(): iterable
    {
        $field = Column::of('name', t('name'), 'e.name');
        $value = Column::live('name', t('name'), static fn (string $row): string => $row);
        $plain = Column::plain('name', t('name'));

        yield 'query by field' => [$field, false, null, false];
        yield 'query by value' => [$value, false, null, true];
        yield 'memory by value' => [$value, true, null, false];
        yield 'memory by field' => [$field, true, null, true];
        yield 'label without order' => [$plain, false, null, true];
        yield 'label in the manual order' => [$plain, false, Order::of('list_order', 'e.position'), false];
        yield 'no label' => [Column::plain('actions'), false, null, false];
    }
}
