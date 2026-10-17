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

final class Version20261016160000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $connected = Table::editor()
            ->setUnquotedName('connected_service')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('account')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('service')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('profile')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_connected_service_profile')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('service', 'profile')->create(),
                Index::editor()->setUnquotedName('uniq_connected_service_user')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('user_id', 'service')->create(),
                Index::editor()->setUnquotedName('idx_connected_service_user')->setUnquotedColumnNames('user_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_connected_service_user')
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTableSQL($connected) as $sql) {
            $this->addSql($sql);
        }
    }
}
