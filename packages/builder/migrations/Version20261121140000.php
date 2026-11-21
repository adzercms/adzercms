<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121140000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $projects = $this->sm->introspectTableByUnquotedName('builder_projects');
        $granted = $projects->edit()
            ->addColumn(Column::editor()->setUnquotedName('access')->setTypeName(Types::STRING)->setLength(16)->setDefaultValue('personal')->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($projects, $granted)) as $sql) {
            $this->addSql($sql);
        }
    }
}
