<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026184500 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $extension = Table::editor()
            ->setUnquotedName('extension')
            ->setColumns(Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables(
            $extension,
            $extension->edit()
                ->addColumn(Column::editor()->setUnquotedName('description_translations')->setTypeName(Types::JSON)->setNotNull(false)->create())
                ->create(),
        )) as $sql) {
            $this->addSql($sql);
        }
    }
}
