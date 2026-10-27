<?php

declare(strict_types=1);

namespace AdzerCMS\MtsMarketolog\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027200100 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM setting WHERE name = ?', ['sms.mtsmarketolog.api_url']);
    }
}
