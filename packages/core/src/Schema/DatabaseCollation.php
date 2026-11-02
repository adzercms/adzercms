<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Types\JsonType;

final readonly class DatabaseCollation
{
    private const string CHARSET = 'utf8mb4';
    private const string MYSQL = 'utf8mb4_0900_as_ci';
    private const string MARIADB = 'utf8mb4_uca1400_as_ci';

    public function __construct(private Connection $connection)
    {
    }

    public function ensure(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            return;
        }

        $collation = $platform instanceof MariaDBPlatform ? self::MARIADB : self::MYSQL;
        $default = \sprintf('CHARACTER SET %s COLLATE %s', self::CHARSET, $collation);
        if ($collation !== $this->connection->fetchOne('SELECT @@collation_database')) {
            $this->connection->executeStatement('ALTER DATABASE '.$default);
        }

        $schema = $this->connection->createSchemaManager();
        $foreign = array_filter($schema->introspectTables(), static fn ($table): bool => $collation !== $table->getOption('collation'));
        if ([] === $foreign) {
            return;
        }

        $comparator = $schema->createComparator();
        $this->connection->executeStatement('SET SESSION foreign_key_checks = 0');
        try {
            foreach ($foreign as $table) {
                $editor = $table->edit();
                foreach ($table->getColumns() as $column) {
                    if (null !== $column->getCollation() && !$column->getType() instanceof JsonType) {
                        $editor->modifyColumn($column->getObjectName(), static function (ColumnEditor $column) use ($collation): void {
                            $column->setCollation($collation);
                        });
                    }
                }

                foreach ($platform->getAlterTableSQL($comparator->compareTables($table, $editor->create())) as $sql) {
                    $this->connection->executeStatement($sql);
                }
                $this->connection->executeStatement(\sprintf('ALTER TABLE %s DEFAULT %s', $table->getObjectName()->toSQL($platform), $default));
            }
        } finally {
            $this->connection->executeStatement('SET SESSION foreign_key_checks = 1');
        }
    }
}
