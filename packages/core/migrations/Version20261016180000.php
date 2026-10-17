<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016180000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $confirmation = $this->sm->introspectTableByUnquotedName('confirmation');
        $serviced = $confirmation->edit()
            ->addColumn(Column::editor()->setUnquotedName('service')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($confirmation, $serviced)) as $sql) {
            $this->addSql($sql);
        }
    }
}
