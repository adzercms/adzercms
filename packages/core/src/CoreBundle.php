<?php

declare(strict_types=1);

namespace AdzerCMS\Core;

use AdzerCMS\Contracts\Account\Identifier;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Contracts\Module\Switchable;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Rule;
use AdzerCMS\Contracts\Ui\Columns;
use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Account\Password;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Asset\ModuleImportMap;
use AdzerCMS\Core\Confirmation\Confirmations;
use AdzerCMS\Core\Notice\MailTransport;
use AdzerCMS\Core\Schema\MigrationOrder;
use AdzerCMS\Core\Security\RecordProvider;
use AdzerCMS\Core\Security\UserChecker;
use AdzerCMS\Core\Security\UserProvider;
use AdzerCMS\Core\Session\Logins;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Text\TextLoader;
use AdzerCMS\Core\Text\Texts;
use AdzerCMS\Core\Ui\Column;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\Version\Comparator;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CoreBundle extends AbstractBundle implements CompilerPassInterface
{
    private const RECONNECT_ATTEMPTS = 1;
    private const TEST_HASHER = ['algorithm' => 'auto', 'cost' => 4, 'time_cost' => 3, 'memory_cost' => 10];
    private const SESSION_GC_PROBABILITY = 1;
    private const FALLBACK_LOCALE = 'en';
    private const LOCALES = [self::FALLBACK_LOCALE, 'ru'];
    private const TEST_MAILER = 'null://null';
    private const LOCKS = 'flock://%kernel.project_dir%/var/lock/%kernel.environment%';
    private const TRANSLATOR = 'adzercms.translator';
    private const TRANSLATOR_DEFAULT = 'translator.default';
    private const IMPORTMAP_READER = 'asset_mapper.importmap.config_reader';
    private const IMPORTMAPS = 'adzercms.importmaps';
    private const IMPORTMAP_VENDOR = 'assets/vendor';

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $mappings = [];
        $migrations = [];
        $templates = [];
        $importmaps = [];
        foreach (self::engineBundles($builder) as $name => ['class' => $class, 'path' => $path, 'namespace' => $namespace]) {
            if (is_dir($path.'/src/Entity')) {
                $mappings[$name] = ['type' => 'attribute', 'is_bundle' => false, 'dir' => $path.'/src/Entity', 'prefix' => $namespace.'\\Entity'];
            }
            if ($builder->fileExists($path.'/importmap.php')) {
                $importmaps[$name] = $path.'/importmap.php';
            }
            if (is_dir($path.'/migrations')) {
                $migrations[$namespace.'\\Migrations'] = $path.'/migrations';
            }
            $kind = is_subclass_of($class, Module::class) ? $class::kind() : null;
            if (null !== $kind && $kind->single() && ucfirst($kind->key()) !== $name && is_dir($path.'/templates')) {
                $templates[$path.'/templates'] = ucfirst($kind->key());
            }
        }

        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'wrapper_class' => Connection::class,
                'options' => ['x_reconnect_attempts' => self::RECONNECT_ATTEMPTS],
            ],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                'identity_generation_preferences' => [PostgreSQLPlatform::class => 'identity'],
                'mappings' => $mappings,
            ],
        ]);

        $builder->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => $migrations,
            'custom_template' => $this->getPath().'/config/migration.tpl',
            'services' => [Comparator::class => MigrationOrder::class],
        ]);

        $test = array_any($builder->getExtensionConfig('framework'), static fn (array $config): bool => true === ($config['test'] ?? null));

        $builder->setParameter(self::IMPORTMAPS, $importmaps);
        $builder->setParameter('adzercms.files_dir', '%kernel.project_dir%/var/files/%kernel.environment%');
        $builder->setParameter('adzercms.session_files', 'file://%adzercms.files_dir%/sessions');
        $builder->prependExtensionConfig('framework', [
            'session' => [
                'handler_id' => '%env(default:adzercms.session_files:SESSION_DSN)%',
                'cookie_lifetime' => Logins::LIFETIME,
                'gc_maxlifetime' => Logins::LIFETIME,
                'gc_probability' => self::SESSION_GC_PROBABILITY,
            ] + ($test ? ['storage_factory_id' => 'session.storage.factory.mock_file'] : []),
            'enabled_locales' => self::LOCALES,
            'translator' => ['fallbacks' => [self::FALLBACK_LOCALE]],
            'mailer' => ['dsn' => $test ? self::TEST_MAILER : MailTransport::DSN],
            'asset_mapper' => ['paths' => [self::IMPORTMAP_VENDOR => 'vendor'], 'missing_import_mode' => 'strict', 'importmap_polyfill' => false],
        ] + (class_exists(LockFactory::class) ? ['lock' => self::LOCKS] : []));

        $builder->prependExtensionConfig('twig', ['globals' => ['brand' => '@'.Brand::class, 'password_min' => Password::MIN_LENGTH], 'paths' => $templates]);

        $hasher = $test ? self::TEST_HASHER : 'auto';
        $builder->prependExtensionConfig('security', [
            'password_hashers' => [PasswordAuthenticatedUserInterface::class => $hasher, Confirmations::HASHER => $hasher],
            'providers' => ['users' => ['id' => UserProvider::class], 'records' => ['id' => RecordProvider::class]],
            'role_hierarchy' => [Role::Admin->security() => [Role::User->security(), 'ROLE_ALLOWED_TO_SWITCH']],
            'firewalls' => ['main' => [
                'lazy' => true,
                'provider' => 'users',
                'user_checker' => UserChecker::class,
                'form_login' => ['login_path' => 'login', 'check_path' => 'login', 'enable_csrf' => true],
                'logout' => ['path' => '/logout'],
                'switch_user' => ['provider' => 'records'],
            ]],
        ]);
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()->set('env(GEOIP_CITY_DB)', '');
        $container->import('../config/services.php');
        $builder->setAlias(self::TRANSLATOR, 'translator')->setPublic(true);
        foreach ([Owner::class, Rule::class, Identifier::class, Switchable::class, ContentSource::class, Fragment::class, Menu::class, Tabs::class, Columns::class] as $contract) {
            $builder->registerForAutoconfiguration($contract)->addTag($contract);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass($this);
    }

    public function process(ContainerBuilder $container): void
    {
        self::texts($container);

        if (!$container->hasDefinition(self::IMPORTMAP_READER)) {
            return;
        }

        $reader = $container->getDefinition(self::IMPORTMAP_READER);
        $container->register(ModuleImportMap::class, ModuleImportMap::class)
            ->setDecoratedService(self::IMPORTMAP_READER)
            ->setArguments([$reader->getArgument(0), $reader->getArgument(1), '%'.self::IMPORTMAPS.'%']);
    }

    public function boot(): void
    {
        $translator = $this->container?->get(self::TRANSLATOR, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        if ($translator instanceof TranslatorInterface) {
            Column::translateWith($translator);
        }
    }

    public static function engine(string $bundle): bool
    {
        return self::class === $bundle || is_subclass_of($bundle, Module::class);
    }

    private static function texts(ContainerBuilder $container): void
    {
        $roots = array_column(self::engineBundles($container), 'path');
        $files = [];

        if ($container->hasDefinition(self::TRANSLATOR_DEFAULT)) {
            $translator = $container->getDefinition(self::TRANSLATOR_DEFAULT);
            foreach ($translator->getArgument(4)['resource_files'] ?? [] as $locale => $paths) {
                foreach ($paths as $path) {
                    if (str_ends_with($path, '.xlf') && array_any($roots, static fn (string $root): bool => str_starts_with($path, $root.'/'))) {
                        $files[$locale][] = $path;
                    }
                }
            }

            foreach ($files as $locale => $paths) {
                foreach (array_unique(array_map(Texts::domain(...), $paths)) as $domain) {
                    $translator->addMethodCall('addResource', [TextLoader::FORMAT, TextLoader::FORMAT, $locale, $domain]);
                }
            }
        }

        $container->setParameter(Texts::FILES, $files);
    }

    /** @return array<string, array{class: class-string, path: string, namespace: string}> */
    private static function engineBundles(ContainerBuilder $builder): array
    {
        /** @var array<string, class-string> $classes */
        $classes = $builder->getParameter('kernel.bundles');
        /** @var array<string, array{path: string, namespace: string}> $metadata */
        $metadata = $builder->getParameter('kernel.bundles_metadata');

        $engine = [];
        foreach ($metadata as $name => $bundle) {
            if (self::engine($classes[$name])) {
                $engine[$name] = ['class' => $classes[$name]] + $bundle;
            }
        }

        return $engine;
    }
}
