<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027163000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $message = $this->sm->introspectTableByUnquotedName('ticket_message');
        $marked = $message->edit()
            ->addColumn(Column::editor()->setUnquotedName('read_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('redacted_files')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($message, $marked)) as $sql) {
            $this->addSql($sql);
        }
    }
}
