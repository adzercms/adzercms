<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Schema;

use AdzerCMS\Core\Schema\MigrationOrder;
use AdzerCMS\Core\Tests\SchemaTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class MigrationsTest extends SchemaTestCase
{
    #[Group('ACMS-CORE-0011')]
    #[Group('ACMS-CORE-0035')]
    #[Group('ACMS-CORE-0156')]
    public function testMigrationsOnEmptyDatabaseBuildSchemaOfMetadata(): void
    {
        $ledger = self::ledger();
        $difference = array_values(array_filter(
            (new SchemaTool($this->em))->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()),
            static fn (string $sql): bool => !str_contains($sql, $ledger),
        ));

        self::assertSame([], $difference, 'migrations diverge from entity metadata');
        self::assertSame(
            [],
            array_values(array_diff(self::foreignCollations($this->em->getConnection()), $this->jsonColumns())),
            'tables and string columns take the database collation',
        );
    }

    #[Group('ACMS-CORE-0013')]
    public function testMigrationsOfSetsGoInOrderOfVersionDate(): void
    {
        $order = self::service(DependencyFactory::class, 'doctrine.migrations.dependency_factory')->getVersionComparator();

        self::assertLessThan(0, $order->compare(
            new Version('AdzerCMS\Zeta\Migrations\Version20260101000000'),
            new Version('AdzerCMS\Alpha\Migrations\Version20260201000000'),
        ));
    }

    #[Group('ACMS-CORE-0013')]
    #[DataProvider('moves')]
    public function testMarkOfMovedMigrationGoesToItsVersionInAnotherSet(bool $markedInNewSet): void
    {
        $dependencies = self::service(DependencyFactory::class, 'doctrine.migrations.dependency_factory');
        $storage = $dependencies->getMetadataStorage();
        $version = $dependencies->getMigrationRepository()->getMigrations()->getItems()[0]->getVersion();
        $applied = $storage->getExecutedMigrations()->getMigration($version);
        $old = new Version('Acme\Renamed\Migrations\Version'.MigrationOrder::moment($version));
        $storage->complete(new ExecutionResult($old, Direction::UP, $applied->getExecutedAt()));
        if (!$markedInNewSet) {
            $storage->complete(new ExecutionResult($version, Direction::DOWN));
        }

        $storage->ensureInitialized();

        $marks = $storage->getExecutedMigrations();
        self::assertFalse($marks->hasMigration($old), 'the mark of the old set is gone');
        self::assertEquals($applied->getExecutedAt(), $marks->getMigration($version)->getExecutedAt(), 'the migration keeps its execution date');
    }

    /** @return iterable<string, array{bool}> */
    public static function moves(): iterable
    {
        yield 'mark of the old set only' => [false];
        yield 'marks of both sets' => [true];
    }

    #[Group('ACMS-CORE-0034')]
    public function testTablesAndColumnsGoUnquotedIntoStatements(): void
    {
        $connection = $this->em->getConnection();
        $refused = [];
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            $columns = $metadata->getColumnNames();
            foreach ($metadata->associationMappings as $association) {
                if ($association instanceof ToOneOwningSideMapping) {
                    array_push($columns, ...array_map(static fn (JoinColumnMapping $join): string => $join->name, $association->joinColumns));
                }
            }

            foreach ($columns as $column) {
                $connection->beginTransaction();
                try {
                    $connection->executeQuery(\sprintf('SELECT %s FROM %s WHERE 1 = 0', $column, $metadata->getTableName()));
                } catch (DriverException) {
                    $refused[] = $metadata->getTableName().'.'.$column;
                } finally {
                    $connection->rollBack();
                }
            }
        }

        self::assertSame([], $refused, 'an insert does not quote names: a table or column takes a reserved word of the database');
    }

    private static function ledger(): string
    {
        $storage = self::service(DependencyFactory::class, 'doctrine.migrations.dependency_factory')
            ->getConfiguration()
            ->getMetadataStorageConfiguration();

        return $storage instanceof TableMetadataStorageConfiguration
            ? $storage->getTableName()
            : self::fail('migration ledger is not a database table');
    }

    /** @return list<string> */
    private function jsonColumns(): array
    {
        $columns = [];
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->fieldMappings as $field) {
                if (Types::JSON === $field->type) {
                    $columns[] = $metadata->getTableName().'.'.$field->columnName;
                }
            }
        }

        return $columns;
    }

    /** @return list<mixed> */
    private static function foreignCollations(Connection $connection): array
    {
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return $connection->fetchFirstColumn(
                'SELECT t.table_name FROM information_schema.tables t'
                .' JOIN information_schema.schemata s ON s.schema_name = t.table_schema'
                .' WHERE t.table_schema = DATABASE() AND t.table_collation <> s.default_collation_name'
                .' UNION SELECT CONCAT(c.table_name, \'.\', c.column_name) FROM information_schema.columns c'
                .' JOIN information_schema.schemata s ON s.schema_name = c.table_schema'
                .' WHERE c.table_schema = DATABASE() AND c.collation_name <> s.default_collation_name',
            );
        }

        return $connection->fetchFirstColumn(
            'SELECT table_name || \'.\' || column_name FROM information_schema.columns'
            .' WHERE table_schema = current_schema() AND collation_name IS NOT NULL',
        );
    }
}
