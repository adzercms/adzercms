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

final class Version20261022124800 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $stage = Table::editor()
            ->setUnquotedName('lead_stage')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('stage_group')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('label')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_lead_stage_group')->setUnquotedColumnNames('stage_group', 'position')->create(),
            )
            ->create();

        $leads = Table::editor()
            ->setUnquotedName('leads')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('due')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('reason')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('arrived')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('staged')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('topic')->setTypeName(Types::STRING)->setLength(190)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('phone')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(190)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('message')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('page')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('stage_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('manager_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_leads_stage_arrived')->setUnquotedColumnNames('stage_id', 'arrived')->create(),
                Index::editor()->setUnquotedName('idx_leads_phone')->setUnquotedColumnNames('phone')->create(),
                Index::editor()->setUnquotedName('idx_leads_email')->setUnquotedColumnNames('email')->create(),
                Index::editor()->setUnquotedName('idx_leads_due')->setUnquotedColumnNames('due')->create(),
                Index::editor()->setUnquotedName('idx_179045522298d193')->setUnquotedColumnNames('stage_id')->create(),
                Index::editor()->setUnquotedName('idx_17904552783e3463')->setUnquotedColumnNames('manager_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_179045522298d193')
                    ->setUnquotedReferencingColumnNames('stage_id')
                    ->setUnquotedReferencedTableName('lead_stage')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_17904552783e3463')
                    ->setUnquotedReferencingColumnNames('manager_id')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$stage, $leads]) as $sql) {
            $this->addSql($sql);
        }
        foreach (['new', 'work', 'done', 'refused'] as $group) {
            $this->addSql('INSERT INTO lead_stage (position, stage_group) VALUES (?, ?)', [1, $group]);
        }
    }
}
