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

final class Version20261016140000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $confirmation = Table::editor()
            ->setUnquotedName('confirmation')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('contact')->setTypeName(Types::STRING)->setLength(254)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('purpose')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('kind')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('secret_hash')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('attempts')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('used_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_confirmation_user')->setUnquotedColumnNames('user_id')->create(),
                Index::editor()->setUnquotedName('idx_confirmation_contact')->setUnquotedColumnNames('contact')->create(),
                Index::editor()->setUnquotedName('idx_confirmation_secret_hash')->setUnquotedColumnNames('secret_hash')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_confirmation_user')
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTableSQL($confirmation) as $sql) {
            $this->addSql($sql);
        }
    }
}
