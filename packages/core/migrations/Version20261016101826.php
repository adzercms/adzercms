<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016101826 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $users = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('comment')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('last_name')->setTypeName(Types::STRING)->setLength(60)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('first_name')->setTypeName(Types::STRING)->setLength(60)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('middle_name')->setTypeName(Types::STRING)->setLength(60)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('note')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('phone')->setTypeName(Types::STRING)->setLength(16)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(254)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('role')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('password')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('theme')->setTypeName(Types::STRING)->setLength(8)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('muted_channels')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('last_login_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('deleted_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_users_phone')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('phone')->create(),
                Index::editor()->setUnquotedName('uniq_users_email')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('email')->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTableSQL($users) as $sql) {
            $this->addSql($sql);
        }
    }
}
