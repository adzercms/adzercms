<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use App\Kernel;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class ModuleBuild
{
    private const CACHE = 'APP_CACHE_DIR';
    private const SURFACE_CACHE = '/var/cache/surface';

    public function __construct(private Registry $registry)
    {
    }

    /**
     * @template T
     *
     * @param list<Installed>     $modules
     * @param \Closure(Kernel): T $use
     *
     * @return T
     */
    public function within(array $modules, ?string $database, \Closure $use): mixed
    {
        [$server, $env] = [$_SERVER, $_ENV];
        ModuleSuite::choose($this->registry, $modules);
        if (null !== $database) {
            $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $database;
        }
        $kernel = new Kernel('test', true);
        $_SERVER[Kernel::SURFACE] = '1';
        $_SERVER[self::CACHE] = $kernel->getProjectDir().self::SURFACE_CACHE;
        try {
            $kernel->boot();

            return $use($kernel);
        } finally {
            $kernel->shutdown();
            [$_SERVER, $_ENV] = [$server, $env];
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function service(Kernel $kernel, string $id, string $class): object
    {
        $container = $kernel->getContainer()->get('test.service_container');
        $service = $container instanceof ContainerInterface ? $container->get($id) : null;

        return $service instanceof $class ? $service : throw new \LogicException(\sprintf('service %s of the test container is not %s', $id, $class));
    }

    public static function createDatabase(KernelInterface $kernel): void
    {
        $create = new CommandTester((new Application($kernel))->find('doctrine:database:create'));
        $create->execute(['--if-not-exists' => true]);
        if (Command::SUCCESS !== $create->getStatusCode()) {
            throw new \LogicException($create->getDisplay());
        }
    }
}
