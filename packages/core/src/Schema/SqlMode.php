<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Schema;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final readonly class SqlMode implements Middleware
{
    public const ENV = 'DATABASE_SQL_MODE';

    public function __construct(private string $mode)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        if ('' === $this->mode) {
            return $driver;
        }

        return new class($driver, $this->mode) extends AbstractDriverMiddleware {
            private const DRIVERS = ['pdo_mysql', 'mysqli'];

            public function __construct(Driver $driver, private readonly string $mode)
            {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                $connection = parent::connect($params);
                if (\in_array($params['driver'] ?? null, self::DRIVERS, true)) {
                    $connection->exec('SET SESSION sql_mode = '.$connection->quote($this->mode));
                }

                return $connection;
            }
        };
    }
}
