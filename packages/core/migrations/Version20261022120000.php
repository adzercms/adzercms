<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022120000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $operation = Table::editor()
            ->setUnquotedName('module_operation')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('state')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('package')->setTypeName(Types::STRING)->setLength(190)->create(),
            )
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_module_request_state')->setUnquotedColumnNames('state', 'id')->create(),
                Index::editor()->setUnquotedName('idx_module_request_package')->setUnquotedColumnNames('package', 'state')->create(),
            )
            ->create();

        $this->addSql($this->platform->getRenameTableSQL($this->platform->quoteSingleIdentifier('module_request'), 'module_operation'));

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables(
            $operation,
            $operation->edit()
                ->renameIndexByUnquotedName('idx_module_request_state', 'idx_module_operation_state')
                ->renameIndexByUnquotedName('idx_module_request_package', 'idx_module_operation_package')
                ->create(),
        )) as $sql) {
            $this->addSql($sql);
        }
    }
}
