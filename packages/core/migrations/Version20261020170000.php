<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020170000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $change = Table::editor()
            ->setUnquotedName('source_change')
            ->setColumns(
                Column::editor()->setUnquotedName('source')->setTypeName(Types::STRING)->setLength(190)->create(),
                Column::editor()->setUnquotedName('moment')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('source')->create())
            ->create();
        foreach ($this->platform->getCreateTablesSQL([$change]) as $sql) {
            $this->addSql($sql);
        }
    }
}
