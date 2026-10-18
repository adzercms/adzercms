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

final class Version20261018160000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $texts = Table::editor()
            ->setUnquotedName('texts')
            ->setColumns(
                Column::editor()->setUnquotedName('locale')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('domain')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('message')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('value')->setTypeName(Types::TEXT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('locale', 'domain', 'message')->create())
            ->create();

        foreach ($this->platform->getCreateTableSQL($texts) as $sql) {
            $this->addSql($sql);
        }
    }
}
