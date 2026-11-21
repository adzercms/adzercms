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

final class Version20261120230000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $blocks = Table::editor()
            ->setUnquotedName('builder_project_blocks')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('style')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('type')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('field_values')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('page_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_builder_project_blocks_page')->setUnquotedColumnNames('page_id', 'position')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_builder_project_blocks_page')
                    ->setUnquotedReferencingColumnNames('page_id')
                    ->setUnquotedReferencedTableName('builder_project_pages')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$blocks]) as $sql) {
            $this->addSql($sql);
        }
    }
}
