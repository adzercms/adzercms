<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016063000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $setting = Table::editor()
            ->setUnquotedName('setting')
            ->setColumns(
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('value')->setTypeName(Types::TEXT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('name')->create())
            ->create();

        foreach ($this->platform->getCreateTableSQL($setting) as $sql) {
            $this->addSql($sql);
        }
    }
}
