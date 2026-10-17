<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Migrations;

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

final class Version20261016230000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $ticket = Table::editor()
            ->setUnquotedName('ticket')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('subject')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('department')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(24)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('last_message_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('closed_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('staff_notified_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_ticket_status')->setUnquotedColumnNames('status', 'last_message_at')->create(),
                Index::editor()->setUnquotedName('idx_97a0ada3a76ed395')->setUnquotedColumnNames('user_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_97a0ada3a76ed395')
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        $ticketAttachment = Table::editor()
            ->setUnquotedName('ticket_attachment')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('stored_name')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('mime')->setTypeName(Types::STRING)->setLength(128)->create(),
                Column::editor()->setUnquotedName('size')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('message_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_efcc4bef537a1329')->setUnquotedColumnNames('message_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_efcc4bef537a1329')
                    ->setUnquotedReferencingColumnNames('message_id')
                    ->setUnquotedReferencedTableName('ticket_message')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        $ticketMessage = Table::editor()
            ->setUnquotedName('ticket_message')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('from_staff')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('body')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('edited_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('ticket_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('author_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_ba71692d700047d2')->setUnquotedColumnNames('ticket_id')->create(),
                Index::editor()->setUnquotedName('idx_ba71692df675f31b')->setUnquotedColumnNames('author_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_ba71692d700047d2')
                    ->setUnquotedReferencingColumnNames('ticket_id')
                    ->setUnquotedReferencedTableName('ticket')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_ba71692df675f31b')
                    ->setUnquotedReferencingColumnNames('author_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$ticket, $ticketAttachment, $ticketMessage]) as $sql) {
            $this->addSql($sql);
        }
    }
}
