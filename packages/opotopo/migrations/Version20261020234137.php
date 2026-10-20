<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020234137 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $item = Table::editor()
            ->setUnquotedName('opotopo_item')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('catalog')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('item_key')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('sku')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('advertisement_id')->setTypeName(Types::GUID)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('fingerprint')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('error')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('error_details')->setTypeName(Types::JSON)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('synced_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_opotopo_item_catalog_item_key')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('catalog', 'item_key')->create(),
                Index::editor()->setUnquotedName('idx_opotopo_item_error')->setUnquotedColumnNames('error')->create(),
            )
            ->create();

        $file = Table::editor()
            ->setUnquotedName('opotopo_file')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('source')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('source_hash')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('gallery')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('category_id')->setTypeName(Types::GUID)->create(),
                Column::editor()->setUnquotedName('size')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('modified_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('checksum')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('file_id')->setTypeName(Types::GUID)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_opotopo_file_target')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('source_hash', 'gallery', 'category_id')->create(),
                Index::editor()->setUnquotedName('uniq_opotopo_file_file_id')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('file_id')->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$item, $file]) as $sql) {
            $this->addSql($sql);
        }
    }
}
