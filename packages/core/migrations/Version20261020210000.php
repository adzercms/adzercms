<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020210000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $request = Table::editor()
            ->setUnquotedName('module_request')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('state')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('filed')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('finished')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('output')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('package')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('action')->setTypeName(Types::STRING)->setLength(16)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_module_request_state')->setUnquotedColumnNames('state', 'id')->create(),
                Index::editor()->setUnquotedName('idx_module_request_package')->setUnquotedColumnNames('package', 'state')->create(),
            )
            ->create();
        foreach ($this->platform->getCreateTablesSQL([$request]) as $sql) {
            $this->addSql($sql);
        }
    }
}
