<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE ticket_message SET read_at = (
            SELECT answered.first_at FROM (
                SELECT said.id, MIN(other.created_at) AS first_at
                FROM ticket_message said
                JOIN ticket_message other ON other.ticket_id = said.ticket_id AND other.from_staff <> said.from_staff AND other.created_at >= said.created_at
                WHERE said.read_at IS NULL
                GROUP BY said.id
            ) answered WHERE answered.id = ticket_message.id
        ) WHERE read_at IS NULL');
    }
}
