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

final class Version20261016104500 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $login = Table::editor()
            ->setUnquotedName('login')
            ->setColumns(
                Column::editor()->setUnquotedName('handle')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('mark')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(254)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('device')->setTypeName(Types::STRING)->setLength(128)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('place')->setTypeName(Types::STRING)->setLength(128)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('address')->setTypeName(Types::STRING)->setLength(45)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('entered_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('seen_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('handle')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_login_user')->setUnquotedColumnNames('user_id')->create(),
                Index::editor()->setUnquotedName('idx_login_seen_at')->setUnquotedColumnNames('seen_at')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_login_user')
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTableSQL($login) as $sql) {
            $this->addSql($sql);
        }
    }
}
