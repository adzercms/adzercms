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

final class Version20261120200000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $projects = Table::editor()
            ->setUnquotedName('builder_projects')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('edited')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(120)->create(),
                Column::editor()->setUnquotedName('owner_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_builder_projects_owner')->setUnquotedColumnNames('owner_id', 'edited')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_builder_projects_owner')
                    ->setUnquotedReferencingColumnNames('owner_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        $pages = Table::editor()
            ->setUnquotedName('builder_project_pages')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('path')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('project_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_builder_project_pages_project')->setUnquotedColumnNames('project_id', 'position')->create(),
                Index::editor()->setUnquotedName('uniq_builder_project_pages_path')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('project_id', 'path')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_builder_project_pages_project')
                    ->setUnquotedReferencingColumnNames('project_id')
                    ->setUnquotedReferencedTableName('builder_projects')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$projects, $pages]) as $sql) {
            $this->addSql($sql);
        }
    }
}
