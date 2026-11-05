<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105120000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $listed = $this->sm->introspectTableByUnquotedName('extension');
        $open = $listed->edit()
            ->addColumn(Column::editor()->setUnquotedName('images')->setTypeName(Types::JSON)->setNotNull(false)->create())
            ->create();
        $closed = $open->edit()
            ->modifyColumnByUnquotedName('images', static function (ColumnEditor $editor): void {
                $editor->setNotNull(true);
            })
            ->create();

        $this->alter($listed, $open);
        $this->addSql("UPDATE extension SET images = '[]'");
        $this->alter($open, $closed);
    }

    private function alter(Table $from, Table $to): void
    {
        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($from, $to)) as $sql) {
            $this->addSql($sql);
        }
    }
}
