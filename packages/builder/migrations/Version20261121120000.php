<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121120000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $transfers = Table::editor()
            ->setUnquotedName('builder_project_transfers')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('project_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('recipient_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('expires')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_builder_project_transfers_project')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('project_id')->create(),
                Index::editor()->setUnquotedName('idx_builder_project_transfers_recipient')->setUnquotedColumnNames('recipient_id', 'expires')->create(),
                Index::editor()->setUnquotedName('idx_builder_project_transfers_expires')->setUnquotedColumnNames('expires')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_builder_project_transfers_project')
                    ->setUnquotedReferencingColumnNames('project_id')
                    ->setUnquotedReferencedTableName('builder_projects')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_builder_project_transfers_recipient')
                    ->setUnquotedReferencingColumnNames('recipient_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$transfers]) as $sql) {
            $this->addSql($sql);
        }
    }
}
