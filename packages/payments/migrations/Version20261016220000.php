<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016220000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $payment = Table::editor()
            ->setUnquotedName('payment')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('number')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('payer')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('items')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->create(),
                Column::editor()->setUnquotedName('method')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('description')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('phone')->setTypeName(Types::STRING)->setLength(16)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(254)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('remembers')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('advance')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('operation_id')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('pay_url')->setTypeName(Types::STRING)->setLength(2048)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('pay_code')->setTypeName(Types::STRING)->setLength(2048)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('back')->setTypeName(Types::STRING)->setLength(2048)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('confirmed_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_6d28840d96901f54')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('number')->create(),
                Index::editor()->setUnquotedName('uniq_6d28840d44ac3583')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('operation_id')->create(),
                Index::editor()->setUnquotedName('idx_payment_status_time')->setUnquotedColumnNames('status', 'created_at')->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$payment]) as $sql) {
            $this->addSql($sql);
        }
    }
}
