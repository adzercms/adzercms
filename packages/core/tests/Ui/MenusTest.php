<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Core\Ui\Menus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\Translation\t;

final class MenusTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function routes(): iterable
    {
        yield 'own route over a shorter prefix of another item' => ['admin_bank_transfers', ['admin_bank_transfers']];
        yield 'longest prefix' => ['admin_bank_transfer', ['admin_bank_transfers']];
        yield 'shorter prefix alone' => ['admin_bank_consent', ['admin_invoices']];
        yield 'no item' => ['admin_users', []];
    }

    /** @param list<string> $active */
    #[Group('ACMS-CORE-0025')]
    #[DataProvider('routes')]
    public function testOneItemOfAreaIsCurrent(string $route, array $active): void
    {
        $menu = new class implements Menu {
            public function items(Area $area): array
            {
                return [
                    new MenuItem('admin_invoices', t('invoices'), 400, ['admin_invoice', 'admin_bank']),
                    new MenuItem('admin_bank_transfers', t('transfers'), 590, ['admin_bank_transfer']),
                ];
            }
        };
        $requests = new RequestStack();
        $requests->push(new Request(attributes: ['_route' => $route]));

        $items = array_merge(...array_column((new Menus([$menu], $requests))->of(Area::Admin->value), 'items'));

        self::assertSame($active, array_values(array_map(
            static fn (array $entry): string => $entry['item']->route,
            array_filter($items, static fn (array $entry): bool => $entry['active']),
        )));
    }
}
