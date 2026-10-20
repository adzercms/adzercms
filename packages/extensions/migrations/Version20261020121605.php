<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Migrations;

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

final class Version20261020121605 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $extension = Table::editor()
            ->setUnquotedName('extension')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('type')->setTypeName(Types::STRING)->setLength(24)->create(),
                Column::editor()->setUnquotedName('price')->setTypeName(Types::DECIMAL)->setPrecision(10)->setScale(2)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('demo')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('version')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('source')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('homepage')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_extension_name')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('name')->create(),
                Index::editor()->setUnquotedName('idx_extension_position')->setUnquotedColumnNames('position')->create(),
            )
            ->create();

        $extensionCategory = Table::editor()
            ->setUnquotedName('extension_category')
            ->setColumns(
                Column::editor()->setUnquotedName('category')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('extension_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('extension_id', 'category')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_extension_category_category')->setUnquotedColumnNames('category')->create(),
                Index::editor()->setUnquotedName('idx_ab6461a9812d5eb')->setUnquotedColumnNames('extension_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_ab6461a9812d5eb')
                    ->setUnquotedReferencingColumnNames('extension_id')
                    ->setUnquotedReferencedTableName('extension')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$extension, $extensionCategory]) as $sql) {
            $this->addSql($sql);
        }
    }
}
