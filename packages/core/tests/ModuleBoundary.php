<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use App\Kernel;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorBagInterface;

final readonly class ModuleBoundary
{
    private const COMMANDS = 'console.command_loader';

    private ModuleBuild $build;

    public function __construct(private Registry $registry, private Installed $module)
    {
        $this->build = new ModuleBuild($registry);
    }

    /**
     * @param list<string> $databases
     *
     * @return list<string>
     */
    public function crossed(array $databases): array
    {
        $with = ModuleSuite::taken($this->registry, [$this->module]);
        $without = $this->without($with);
        $before = $this->build->within($without, null, self::surface(...));
        $after = $this->build->within($with, null, self::surface(...));

        $crossed = [];
        foreach (array_intersect_key($before['services'], $after['services']) as $id => $class) {
            if ($after['services'][$id] !== $class) {
                $crossed[] = \sprintf('service %s: %s instead of %s', $id, $after['services'][$id], $class);
            }
        }
        foreach ($before['routes'] as $name => $route) {
            if (self::route($route) !== self::route($after['routes'][$name] ?? null)) {
                $crossed[] = \sprintf('route %s: %s instead of %s', $name, self::route($after['routes'][$name] ?? null), self::route($route));
            }
        }
        foreach (array_diff_key($after['routes'], $before['routes']) as $name => $route) {
            foreach ($before['routes'] as $prior => $taken) {
                if (self::overlaps($route, $taken)) {
                    $crossed[] = \sprintf('route %s: takes path %s of route %s', $name, $taken->getPath(), $prior);
                }
            }
        }
        foreach ($before['commands'] as $name => $service) {
            if ($service !== ($after['commands'][$name] ?? null)) {
                $crossed[] = \sprintf('command %s: %s instead of %s', $name, $after['commands'][$name] ?? 'none', $service);
            }
        }
        foreach ($before['messages'] as $key => $message) {
            if ($message !== ($after['messages'][$key] ?? null)) {
                $crossed[] = \sprintf('message %s: changed', $key);
            }
        }
        foreach ($databases as $database) {
            array_push($crossed, ...$this->schema($database, $without, $with));
        }

        return $crossed;
    }

    /**
     * @param list<Installed> $with
     *
     * @return list<Installed>
     */
    private function without(array $with): array
    {
        $kept = array_values(array_filter($with, fn (Installed $module): bool => $module->package !== $this->module->package));
        do {
            $count = \count($kept);
            $packages = array_column($kept, 'package');
            $kinds = array_column($kept, 'kind');
            $kept = array_values(array_filter($kept, fn (Installed $module): bool => [] === array_diff($module->requires, $packages)
                && [] === array_filter($this->registry->required($module), static fn (Kind $kind): bool => !\in_array($kind, $kinds, true))));
        } while (\count($kept) !== $count);

        return $kept;
    }

    /**
     * @param list<Installed> $without
     * @param list<Installed> $with
     *
     * @return list<string>
     */
    private function schema(string $database, array $without, array $with): array
    {
        ModuleSuite::occupy();
        $before = $this->build->within($without, $database, static function (Kernel $kernel): Schema {
            $em = ModuleBuild::service($kernel, 'doctrine.orm.entity_manager', EntityManagerInterface::class);
            (new SchemaTool($em))->dropDatabase();
            self::migrate($kernel, '');

            return $em->getConnection()->createSchemaManager()->introspectSchema();
        });
        $namespace = (new \ReflectionClass($this->module->bundle))->getNamespaceName();

        return $this->build->within($with, $database, static function (Kernel $kernel) use ($before, $namespace, $database): array {
            self::migrate($kernel, $namespace.'\\Migrations\\');
            $em = ModuleBuild::service($kernel, 'doctrine.orm.entity_manager', EntityManagerInterface::class);
            $own = [];
            foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
                if (str_starts_with($metadata->getName(), $namespace.'\\Entity\\')) {
                    $own[] = strtolower($metadata->getTableName());
                    foreach ($metadata->associationMappings as $association) {
                        if ($association instanceof ManyToManyOwningSideMapping) {
                            $own[] = strtolower($association->joinTable->name);
                        }
                    }
                }
            }
            $schemas = $em->getConnection()->createSchemaManager();
            $diff = $schemas->createComparator()->compareSchemas($before, $schemas->introspectSchema());

            $crossed = [];
            foreach ([
                'created' => $diff->getCreatedTables(),
                'altered' => array_map(static fn (TableDiff $table): Table => $table->getOldTable(), $diff->getAlteredTables()),
                'dropped' => $diff->getDroppedTables(),
            ] as $change => $tables) {
                foreach ($tables as $table) {
                    $name = strtolower($table->getObjectName()->getUnqualifiedName()->getValue());
                    if (!\in_array($name, $own, true)) {
                        $crossed[] = \sprintf('table %s: %s on %s', $name, $change, strtok($database, ':'));
                    }
                }
            }

            return $crossed;
        });
    }

    /** @return array{services: array<string, string>, routes: array<string, Route>, commands: array<string, string>, messages: array<string, string>} */
    private static function surface(Kernel $kernel): array
    {
        $dump = $kernel->getContainer()->getParameter('debug.container.dump');
        $builder = \is_string($dump) && is_file($serialized = substr_replace($dump, '.ser', -4))
            ? unserialize((string) file_get_contents($serialized), ['allowed_classes' => true])
            : null;
        if (!$builder instanceof ContainerBuilder) {
            throw new \LogicException('the build has no serialized container dump of a debug build');
        }

        $services = [];
        foreach ([...array_keys($builder->getDefinitions()), ...array_keys($builder->getAliases())] as $id) {
            try {
                $services[$id] = (string) $builder->findDefinition($id)->getClass();
            } catch (ServiceNotFoundException) {
                $services[$id] = '';
            }
        }

        $commands = [];
        $map = $builder->getDefinition(self::COMMANDS)->getArgument(1);
        foreach (\is_array($map) ? $map : [] as $name => $service) {
            $commands[(string) $name] = \is_string($service) ? $service : '';
        }

        $messages = [];
        $translator = ModuleBuild::service($kernel, 'translator', TranslatorBagInterface::class);
        $locales = $kernel->getContainer()->getParameter('kernel.enabled_locales');
        foreach (\is_array($locales) ? $locales : [] as $locale) {
            foreach ($translator->getCatalogue((string) $locale)->all() as $domain => $units) {
                foreach ($units as $key => $message) {
                    $messages[\sprintf('%s %s %s', $locale, $domain, $key)] = $message;
                }
            }
        }

        return [
            'services' => $services,
            'routes' => ModuleBuild::service($kernel, 'router', RouterInterface::class)->getRouteCollection()->all(),
            'commands' => $commands,
            'messages' => $messages,
        ];
    }

    private static function migrate(Kernel $kernel, string $set): void
    {
        $migrations = ModuleBuild::service($kernel, 'doctrine.migrations.dependency_factory', DependencyFactory::class);
        $storage = $migrations->getMetadataStorage();
        $storage->ensureInitialized();
        $executed = $storage->getExecutedMigrations();
        $versions = [];
        foreach ($migrations->getMigrationRepository()->getMigrations()->getItems() as $migration) {
            $version = $migration->getVersion();
            if (!$executed->hasMigration($version) && str_starts_with((string) $version, $set)) {
                $versions[] = $version;
            }
        }
        if ([] !== $versions) {
            $migrations->getMigrator()->migrate($migrations->getMigrationPlanCalculator()->getPlanForVersions($versions, Direction::UP), new MigratorConfiguration());
        }
    }

    private static function route(?Route $route): string
    {
        return null === $route ? 'none' : implode(' ', [
            $route->getPath(),
            $route->getHost(),
            implode('|', $route->getMethods()),
            implode('|', $route->getSchemes()),
            (string) json_encode($route->getDefault('_controller')),
        ]);
    }

    private static function overlaps(Route $route, Route $taken): bool
    {
        $path = static fn (Route $route): string => (string) preg_replace('~\{[^}]+\}~', '{}', $route->getPath());

        return $path($route) === $path($taken)
            && ('' === $route->getHost() || '' === $taken->getHost() || $route->getHost() === $taken->getHost())
            && ([] === $route->getMethods() || [] === $taken->getMethods() || [] !== array_intersect($route->getMethods(), $taken->getMethods()));
    }
}
