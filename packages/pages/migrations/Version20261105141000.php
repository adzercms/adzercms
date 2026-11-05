<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Migrations;

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

final class Version20261105141000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $pages = Table::editor()
            ->setUnquotedName('pages')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('published')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('menu')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('menu_label')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('path')->setTypeName(Types::STRING)->setLength(190)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_pages_position')->setUnquotedColumnNames('position')->create(),
                Index::editor()->setUnquotedName('uniq_pages_path')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('path')->create(),
            )
            ->create();

        $blocks = Table::editor()
            ->setUnquotedName('page_blocks')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('type')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('field_values')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('page_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_page_blocks_page')->setUnquotedColumnNames('page_id', 'position')->create(),
                Index::editor()->setUnquotedName('idx_a13b6366c4663e4')->setUnquotedColumnNames('page_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_a13b6366c4663e4')
                    ->setUnquotedReferencingColumnNames('page_id')
                    ->setUnquotedReferencedTableName('pages')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$pages, $blocks]) as $sql) {
            $this->addSql($sql);
        }
    }
}
