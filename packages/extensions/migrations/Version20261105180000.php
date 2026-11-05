<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105180000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $listed = $this->sm->introspectTableByUnquotedName('extension');
        $titled = $listed->edit()
            ->addColumn(Column::editor()->setUnquotedName('title')->setTypeName(Types::TEXT)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('title_translations')->setTypeName(Types::JSON)->setNotNull(false)->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($listed, $titled)) as $sql) {
            $this->addSql($sql);
        }
    }
}
