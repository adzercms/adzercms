<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Migrations;

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

final class Version20261022160000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $entry = Table::editor()
            ->setUnquotedName('lead_entry')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('written')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('note')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('changes')->setTypeName(Types::JSON)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('lead_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('author_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_4dc650ff55458d')->setUnquotedColumnNames('lead_id')->create(),
                Index::editor()->setUnquotedName('idx_4dc650fff675f31b')->setUnquotedColumnNames('author_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_4dc650ff55458d')
                    ->setUnquotedReferencingColumnNames('lead_id')
                    ->setUnquotedReferencedTableName('leads')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_4dc650fff675f31b')
                    ->setUnquotedReferencingColumnNames('author_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$entry]) as $sql) {
            $this->addSql($sql);
        }
    }
}
