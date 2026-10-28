<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $payment = $this->sm->introspectTableByUnquotedName('payment');
        $charged = $payment->edit()
            ->addColumn(Column::editor()->setUnquotedName('fee')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(2)->setNotNull(false)->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($payment, $charged)) as $sql) {
            $this->addSql($sql);
        }
    }
}
