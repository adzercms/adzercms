<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114150000 extends AbstractMigration
{
    private const REVISION = 'legal.revision';
    private const DATE = 'Y-m-d';

    public function up(Schema $schema): void
    {
        $revision = trim((string) $this->connection->fetchOne('SELECT value FROM setting WHERE name = ?', [self::REVISION]));
        $day = \DateTimeImmutable::createFromFormat('!'.self::DATE, $revision);
        if (false !== $day && $day->format(self::DATE) === $revision) {
            $this->addSql('INSERT INTO setting (name, value) VALUES (?, ?)', ['legal.editions', json_encode([['effective' => $revision]], \JSON_THROW_ON_ERROR)]);
        }
        $this->addSql('DELETE FROM setting WHERE name = ?', [self::REVISION]);
    }
}
