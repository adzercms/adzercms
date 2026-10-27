<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027120000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $author = Table::editor()
            ->setUnquotedName('extension_author')
            ->setColumns(
                Column::editor()->setUnquotedName('author_key')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_extension_author_key')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('author_key')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_9273e06ba76ed395')
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$author]) as $sql) {
            $this->addSql($sql);
        }

        $listed = $this->sm->introspectTableByUnquotedName('extension');
        $open = $listed->edit()
            ->addColumn(Column::editor()->setUnquotedName('author_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('decision')->setTypeName(Types::STRING)->setLength(16)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('reason')->setTypeName(Types::TEXT)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('author_key')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_9fb73d77f675f31b')->setUnquotedColumnNames('author_id')->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_extension_decision')->setUnquotedColumnNames('decision', 'position')->create())
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_9fb73d77f675f31b')
                    ->setUnquotedReferencingColumnNames('author_id')
                    ->setUnquotedReferencedTableName('extension_author')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();
        $closed = $open->edit()
            ->modifyColumnByUnquotedName('decision', static function (ColumnEditor $editor): void {
                $editor->setNotNull(true);
            })
            ->create();

        $this->alter($listed, $open);
        $this->addSql("UPDATE extension SET decision = 'published'");
        $this->alter($open, $closed);
    }

    private function alter(Table $from, Table $to): void
    {
        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($from, $to)) as $sql) {
            $this->addSql($sql);
        }
    }
}
