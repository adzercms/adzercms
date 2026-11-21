<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121200000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $projects = $this->sm->introspectTableByUnquotedName('builder_projects');
        $open = $projects->edit()
            ->addColumn(Column::editor()->setUnquotedName('token')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
            ->create();
        $closed = $open->edit()
            ->modifyColumnByUnquotedName('token', static function (ColumnEditor $editor): void {
                $editor->setNotNull(true);
            })
            ->addIndex(Index::editor()->setUnquotedName('uniq_builder_projects_token')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('token')->create())
            ->create();

        $this->alter($projects, $open);
        $this->addSql($this->platform instanceof PostgreSQLPlatform
            ? "UPDATE builder_projects SET token = REPLACE(CAST(gen_random_uuid() AS VARCHAR), '-', '')"
            : 'UPDATE builder_projects SET token = LOWER(HEX(RANDOM_BYTES(16)))');
        $this->alter($open, $closed);
    }

    private function alter(Table $from, Table $to): void
    {
        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($from, $to)) as $sql) {
            $this->addSql($sql);
        }
    }
}
