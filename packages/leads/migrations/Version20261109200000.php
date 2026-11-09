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

final class Version20261109200000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $attachment = Table::editor()
            ->setUnquotedName('lead_attachment')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('stored_name')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('mime')->setTypeName(Types::STRING)->setLength(128)->create(),
                Column::editor()->setUnquotedName('size')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('lead_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_51c4c22755458d')->setUnquotedColumnNames('lead_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_51c4c22755458d')
                    ->setUnquotedReferencingColumnNames('lead_id')
                    ->setUnquotedReferencedTableName('leads')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTableSQL($attachment) as $sql) {
            $this->addSql($sql);
        }
    }
}
