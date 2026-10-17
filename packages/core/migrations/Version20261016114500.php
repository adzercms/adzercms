<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

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

final class Version20261016114500 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $notice = Table::editor()
            ->setUnquotedName('notice')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('event')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('channel')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('recipient')->setTypeName(Types::STRING)->setLength(254)->create(),
                Column::editor()->setUnquotedName('body')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('error')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('idempotency')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_notice_idempotency')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('idempotency')->create(),
                Index::editor()->setUnquotedName('idx_notice_user')->setUnquotedColumnNames('user_id')->create(),
                Index::editor()->setUnquotedName('idx_notice_created_at')->setUnquotedColumnNames('created_at')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_notice_user')
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTableSQL($notice) as $sql) {
            $this->addSql($sql);
        }
    }
}
