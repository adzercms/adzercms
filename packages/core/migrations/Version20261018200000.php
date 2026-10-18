<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018200000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $users = $this->sm->introspectTableByUnquotedName('users');
        $indexed = $users->edit()
            ->addIndex(Index::editor()->setUnquotedName('idx_users_role_status')->setUnquotedColumnNames('role', 'status')->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($users, $indexed)) as $sql) {
            $this->addSql($sql);
        }
    }
}
