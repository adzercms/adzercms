<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016185000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $notice = $this->sm->introspectTableByUnquotedName('notice');
        $indexed = $notice->edit()
            ->addIndex(Index::editor()->setUnquotedName('idx_notice_channel_created_at')->setUnquotedColumnNames('channel', 'created_at')->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($notice, $indexed)) as $sql) {
            $this->addSql($sql);
        }
    }
}
