<?php

declare(strict_types=1);

namespace <namespace>;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class <className> extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(true, 'generated migration is not finished: schema change by a Table object and platform SQL, data transfer by hand');
<up>
    }
}
