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

final class Version20261119150000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $menu = Table::editor()
            ->setUnquotedName('page_menu')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('section')->setTypeName(Types::STRING)->setLength(190)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('label')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('address')->setTypeName(Types::STRING)->setLength(2048)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('page_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_page_menu_page')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('page_id')->create(),
                Index::editor()->setUnquotedName('uniq_page_menu_section')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('section')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_fac12649c4663e4')
                    ->setUnquotedReferencingColumnNames('page_id')
                    ->setUnquotedReferencedTableName('pages')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$menu]) as $sql) {
            $this->addSql($sql);
        }
        $this->addSql('INSERT INTO page_menu (position, page_id) SELECT ROW_NUMBER() OVER (ORDER BY position, id), id FROM pages WHERE menu = TRUE');

        $pages = $this->sm->introspectTableByUnquotedName('pages');
        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($pages, $pages->edit()->dropColumnByUnquotedName('menu')->create())) as $sql) {
            $this->addSql($sql);
        }
    }
}
