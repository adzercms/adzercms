<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116075000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $source = Table::editor()
            ->setUnquotedName('opotopo_git_source')
            ->setColumns(
                Column::editor()->setUnquotedName('repository_url')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('deploy_key')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('webhook_url')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('failures')->setTypeName(Types::JSON)->create(),
                Column::editor()->setUnquotedName('item_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create())
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_289955a6126f525e')
                    ->setUnquotedReferencingColumnNames('item_id')
                    ->setUnquotedReferencedTableName('opotopo_item')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        foreach ($this->platform->getCreateTablesSQL([$source]) as $sql) {
            $this->addSql($sql);
        }
    }
}
