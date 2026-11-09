<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109210000 extends AbstractMigration
{
    private const COLUMNS = ['origin_source' => 64, 'origin_medium' => 64, 'origin_campaign' => 64, 'origin_term' => 128, 'origin_click' => 64];

    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $leads = $this->sm->introspectTableByUnquotedName('leads');
        $editor = $leads->edit();
        foreach (self::COLUMNS as $name => $length) {
            $editor->addColumn(Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(false)->create());
        }

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($leads, $editor->create())) as $sql) {
            $this->addSql($sql);
        }
    }
}
