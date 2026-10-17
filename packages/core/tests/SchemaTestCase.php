<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

abstract class SchemaTestCase extends WebTestCase
{
    use ClockSensitiveTrait;
    use Container;

    private static bool $migrated = false;

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->em = self::service(EntityManagerInterface::class);

        if (!self::$migrated) {
            (new SchemaTool($this->em))->dropDatabase();
            $migrate = self::command('doctrine:migrations:migrate');
            $migrate->execute([], ['interactive' => false]);
            self::assertSame(Command::SUCCESS, $migrate->getStatusCode(), $migrate->getDisplay());
            self::$migrated = true;
        }

        self::service(CacheClearerInterface::class, 'cache.app_clearer')->clear($this->client->getKernel()->getCacheDir());
        (new Filesystem())->remove(self::parameter('adzercms.files_dir'));
        $this->em->getConnection()->beginTransaction();
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    protected function reread(object $entity): object
    {
        if ($this->em->contains($entity)) {
            $this->em->refresh($entity);

            return $entity;
        }

        return $this->em->find($entity::class, $this->em->getClassMetadata($entity::class)->getIdentifierValues($entity)) ?? self::fail(\sprintf('%s is gone', $entity::class));
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }
}
