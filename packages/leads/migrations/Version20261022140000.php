<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022140000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $leads = $this->sm->introspectTableByUnquotedName('leads');
        $widened = $leads->edit()
            ->modifyColumnByUnquotedName('email', static fn (ColumnEditor $email): ColumnEditor => $email->setLength(254))
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($leads, $widened)) as $sql) {
            $this->addSql($sql);
        }
    }
}
