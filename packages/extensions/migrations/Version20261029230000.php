<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029230000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $listed = $this->sm->introspectTableByUnquotedName('extension');
        $open = $listed->edit()
            ->addColumn(Column::editor()->setUnquotedName('state')->setTypeName(Types::STRING)->setLength(16)->setNotNull(false)->create())
            ->create();
        $closed = $open->edit()
            ->modifyColumnByUnquotedName('state', static function (ColumnEditor $editor): void {
                $editor->setNotNull(true);
            })
            ->addIndex(Index::editor()->setUnquotedName('idx_extension_state')->setUnquotedColumnNames('state', 'position')->create())
            ->create();

        $this->alter($listed, $open);
        $this->addSql("UPDATE extension SET state = decision WHERE decision <> 'published'");
        $this->addSql("UPDATE extension SET state = 'missing' WHERE state IS NULL AND version IS NULL");
        $this->addSql("UPDATE extension SET state = 'unclaimed' WHERE state IS NULL AND author_id IS NOT NULL
            AND (author_key IS NULL OR author_key <> (SELECT holder.author_key FROM extension_author holder WHERE holder.user_id = extension.author_id))");
        $this->addSql("UPDATE extension SET state = 'published' WHERE state IS NULL");
        $this->alter($open, $closed);
    }

    private function alter(Table $from, Table $to): void
    {
        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($from, $to)) as $sql) {
            $this->addSql($sql);
        }
    }
}
