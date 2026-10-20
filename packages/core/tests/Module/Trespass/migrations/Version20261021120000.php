<?php

declare(strict_types=1);

namespace AdzerCMS\Trespass\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021120000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $tables = [];
        foreach (['trespass_visit', 'visit'] as $name) {
            $tables[] = Table::editor()
                ->setUnquotedName($name)
                ->setColumns(Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->create();
        }

        foreach ($this->platform->getCreateTablesSQL($tables) as $sql) {
            $this->addSql($sql);
        }
    }
}
