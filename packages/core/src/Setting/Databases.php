<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Databases
{
    /** @var array<string, Connection> */
    private array $connections = [];

    public function __construct(
        private readonly Settings $settings,
        #[Autowire(service: 'doctrine.dbal.connection_factory')]
        private readonly ConnectionFactory $factory,
    ) {
    }

    public function connection(string $name): ?Connection
    {
        $url = $this->settings->get($name);

        return '' === $url ? null : $this->connections[$url] ??= $this->factory->createConnection(['url' => $url]);
    }
}
