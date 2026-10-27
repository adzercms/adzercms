<?php

declare(strict_types=1);

namespace AdzerCMS\YooKassa\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027200200 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM setting WHERE name = ?', ['payment.yookassa.api_url']);
    }
}
