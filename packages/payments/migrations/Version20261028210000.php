<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028210000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $payment = $this->sm->introspectTableByUnquotedName('payment');
        $open = $payment->edit()
            ->addColumn(Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_payment_status_expiry')->setUnquotedColumnNames('status', 'expires_at')->create())
            ->create();
        $closed = $open->edit()
            ->modifyColumnByUnquotedName('expires_at', static function (ColumnEditor $editor): void {
                $editor->setNotNull(true);
            })
            ->create();

        $this->alter($payment, $open);
        $this->addSql('UPDATE payment SET expires_at = '.$this->platform->getDateAddDaysExpression('created_at', '1'));
        $this->alter($open, $closed);
    }

    private function alter(Table $from, Table $to): void
    {
        foreach ($this->platform->getAlterTableSQL($this->sm->createComparator()->compareTables($from, $to)) as $sql) {
            $this->addSql($sql);
        }
    }
}
