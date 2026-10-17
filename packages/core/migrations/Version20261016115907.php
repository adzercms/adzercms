<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016115907 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $notice = $this->sm->introspectTableByUnquotedName('notice');
        $deferred = $notice->edit()
            ->addColumn(Column::editor()->setUnquotedName('subject')->setTypeName(Types::TEXT)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('html')->setTypeName(Types::TEXT)->setNotNull(false)->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_notice_status')->setUnquotedColumnNames('status')->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($notice, $deferred)) as $sql) {
            $this->addSql($sql);
        }
    }
}
