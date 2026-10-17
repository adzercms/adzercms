<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $payment = $this->sm->introspectTableByUnquotedName('payment');
        $indexed = $payment->edit()
            ->addIndex(Index::editor()->setUnquotedName('idx_payment_payer')->setUnquotedColumnNames('payer')->create())
            ->create();

        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($payment, $indexed)) as $sql) {
            $this->addSql($sql);
        }
    }
}
