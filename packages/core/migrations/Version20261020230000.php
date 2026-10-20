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

final class Version20261020230000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $source = Table::editor()
            ->setUnquotedName('module_source')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('login')->setTypeName(Types::STRING)->setLength(190)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('token_cipher')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('url')->setTypeName(Types::STRING)->setLength(190)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_module_source_url')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('url')->create(),
            )
            ->create();
        foreach ($this->platform->getCreateTablesSQL([$source]) as $sql) {
            $this->addSql($sql);
        }
    }
}
