<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

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

final class Version20261016190000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $consent = Table::editor()
            ->setUnquotedName('consent')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('kind')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('revision')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('address')->setTypeName(Types::STRING)->setLength(45)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_63120810a76ed395')->setUnquotedColumnNames('user_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_63120810a76ed395')
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$consent]) as $sql) {
            $this->addSql($sql);
        }
    }
}
