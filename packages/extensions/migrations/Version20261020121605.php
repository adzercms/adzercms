<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020121605 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $extension = Table::editor()
            ->setUnquotedName('extension')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('type')->setTypeName(Types::STRING)->setLength(24)->create(),
                Column::editor()->setUnquotedName('price')->setTypeName(Types::DECIMAL)->setPrecision(10)->setScale(2)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('demo')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_extension_name')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('name')->create(),
                Index::editor()->setUnquotedName('idx_extension_position')->setUnquotedColumnNames('position')->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$extension]) as $sql) {
            $this->addSql($sql);
        }
    }
}
