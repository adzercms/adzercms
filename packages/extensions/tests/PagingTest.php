<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\Request;

final class PagingTest extends CatalogTestCase
{
    #[Group('ACMS-UI-0014')]
    #[DataProvider('joins')]
    /** @param \Closure(ExtensionRepository, Table): list<Extension> $listing */
    public function testListingPagesAndCountsByItsJoins(\Closure $listing, int $statements, bool $distinct): void
    {
        foreach (['acme/one', 'acme/two', 'acme/three'] as $name) {
            $this->entry($name, package: self::package('Kit', categories: [Category::Commerce, Category::Content]));
        }
        $this->em->clear();
        $table = Table::of(new Request(['page' => '2']), 'extensions', [], '', size: 2);
        $holder = self::service(DebugDataHolder::class, 'doctrine.debug_data_holder');
        $holder->reset();

        $rows = $listing(self::extensions(), $table);

        $sql = array_column(array_merge(...array_values($holder->getData())), 'sql');
        $counts = array_values(array_filter($sql, static fn (string $statement): bool => false !== stripos($statement, 'count(')));
        self::assertSame([1, 2, $statements, [$distinct]], [
            \count($rows),
            $table->pages(),
            \count($sql),
            array_map(static fn (string $count): bool => false !== stripos($count, 'distinct'), $counts),
        ]);
    }

    /** @return iterable<string, array{\Closure(ExtensionRepository, Table): list<Extension>, int, bool}> */
    public static function joins(): iterable
    {
        yield 'collection' => [static fn (ExtensionRepository $extensions, Table $table): array => $extensions->published($table), 3, true];
        yield 'single records' => [static fn (ExtensionRepository $extensions, Table $table): array => $extensions->page($table), 2, false];
    }
}
