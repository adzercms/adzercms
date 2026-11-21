<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121160000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $projects = $this->sm->introspectTableByUnquotedName('builder_projects');
        $covered = $projects->edit()
            ->addColumn(Column::editor()->setUnquotedName('cover')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
            ->create();
        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($projects, $covered)) as $sql) {
            $this->addSql($sql);
        }

        $versions = Table::editor()
            ->setUnquotedName('builder_project_versions')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('project_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(120)->create(),
                Column::editor()->setUnquotedName('decision')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('reason')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('pages')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('cover')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('submitted')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_builder_project_versions_project')->setUnquotedColumnNames('project_id', 'decision')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_builder_project_versions_project')
                    ->setUnquotedReferencingColumnNames('project_id')
                    ->setUnquotedReferencedTableName('builder_projects')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();
        foreach ($this->platform->getCreateTablesSQL([$versions]) as $sql) {
            $this->addSql($sql);
        }
    }
}
