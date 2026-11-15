<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115150000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $users = $this->sm->introspectTableByUnquotedName('users');
        $pictured = $users->edit()
            ->addColumn(Column::editor()->setUnquotedName('avatar')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($users, $pictured)) as $sql) {
            $this->addSql($sql);
        }
    }
}
