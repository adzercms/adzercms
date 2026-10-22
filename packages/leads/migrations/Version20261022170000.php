<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022170000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $leads = $this->sm->introspectTableByUnquotedName('leads');
        $indexed = $leads->edit()
            ->addIndex(Index::editor()->setUnquotedName('idx_leads_arrived')->setUnquotedColumnNames('arrived')->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($leads, $indexed)) as $sql) {
            $this->addSql($sql);
        }
    }
}
