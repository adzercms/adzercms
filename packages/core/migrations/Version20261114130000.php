<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114130000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $changes = $this->sm->introspectTableByUnquotedName('source_change');
        $announced = $changes->edit()
            ->addColumn(Column::editor()->setUnquotedName('announced')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($changes, $announced)) as $sql) {
            $this->addSql($sql);
        }
    }
}
