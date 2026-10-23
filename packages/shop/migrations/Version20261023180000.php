<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Migrations;

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

final class Version20261023180000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $order = Table::editor()
            ->setUnquotedName('shop_order')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('page_key')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('payment_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('paid')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('arrived')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('staged')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('phone')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(254)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('receipt')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('point_key')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('point_address')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('address')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('comment')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('payment')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('delivery_price')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('total')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('uniq_shop_order_key')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('page_key')->create(),
                Index::editor()->setUnquotedName('idx_shop_order_status_arrived')->setUnquotedColumnNames('status', 'arrived')->create(),
                Index::editor()->setUnquotedName('idx_shop_order_arrived')->setUnquotedColumnNames('arrived')->create(),
                Index::editor()->setUnquotedName('idx_shop_order_phone')->setUnquotedColumnNames('phone')->create(),
                Index::editor()->setUnquotedName('idx_shop_order_email')->setUnquotedColumnNames('email')->create(),
                Index::editor()->setUnquotedName('idx_shop_order_payment')->setUnquotedColumnNames('payment_id')->create(),
            )
            ->create();

        $item = Table::editor()
            ->setUnquotedName('shop_order_item')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('order_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('variant')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('price')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->create(),
                Column::editor()->setUnquotedName('quantity')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_shop_order_item_order')->setUnquotedColumnNames('order_id')->create(),
                Index::editor()->setUnquotedName('idx_shop_order_item_product')->setUnquotedColumnNames('product_id')->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_shop_order_item_order')
                    ->setUnquotedReferencingColumnNames('order_id')
                    ->setUnquotedReferencedTableName('shop_order')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_shop_order_item_product')
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('shop_product')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->create();

        foreach ([$order, $item] as $table) {
            foreach ($this->platform->getCreateTableSQL($table) as $sql) {
                $this->addSql($sql);
            }
        }
    }
}
