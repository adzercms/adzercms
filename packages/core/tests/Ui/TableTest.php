<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
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
    #[DataProvider('cuts')]
    public function testLeadRowsStayAboveTheSortedRecordsAndGoThroughTheSearch(array $query, array $expected): void
    {
        $table = Table::of(new Request($query), 'list', [Column::live('name', t('name'), static fn (string $row): string => $row)], 'name',
            filters: [Filter::search()], live: true);

        self::assertSame($expected, $table->rows(['beta', 'alpha', 'gamma'], ['zeta']));
    }

    /** @return iterable<string, array{array<string, string>, list<string>}> */
    public static function cuts(): iterable
    {
        yield 'ascending' => [[], ['zeta', 'alpha', 'beta', 'gamma']];
        yield 'descending' => [['sort' => 'name', 'order' => 'desc'], ['zeta', 'gamma', 'beta', 'alpha']];
        yield 'search past the lead' => [['q' => 'al'], ['alpha']];
        yield 'search of the lead' => [['q' => 'z'], ['zeta']];
    }
}
