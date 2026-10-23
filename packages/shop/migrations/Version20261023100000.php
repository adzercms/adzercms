<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Migrations;

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

final class Version20261023100000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $category = Table::editor()
            ->setUnquotedName('shop_category')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_shop_category_position')->setUnquotedColumnNames('position')->create(),
            )
            ->create();

        $product = Table::editor()
            ->setUnquotedName('shop_product')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('category_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('price')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('active')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_shop_product_category')->setUnquotedColumnNames('category_id')->create(),
                Index::editor()->setUnquotedName('idx_shop_product_active_position')->setUnquotedColumnNames('active', 'position')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_shop_product_category')
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('shop_category')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $variant = Table::editor()
            ->setUnquotedName('shop_variant')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('price')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_shop_variant_product')->setUnquotedColumnNames('product_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_shop_variant_product')
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('shop_product')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        $photo = Table::editor()
            ->setUnquotedName('shop_photo')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('file')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_shop_photo_product')->setUnquotedColumnNames('product_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_shop_photo_product')
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('shop_product')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ([$category, $product, $variant, $photo] as $table) {
            foreach ($this->platform->getCreateTableSQL($table) as $sql) {
                $this->addSql($sql);
            }
        }
    }
}
