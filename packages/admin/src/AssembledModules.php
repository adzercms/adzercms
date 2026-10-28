<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Core\Composer\Description;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Setting\Catalog;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class AssembledModules
{
    /**
     * @param array<string, class-string> $bundles
     * @param iterable<Guided>            $guides
     */
    public function __construct(
        #[Autowire('%kernel.bundles%')]
        private array $bundles,
        private Catalog $settings,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private DependencyFactory $migrations,
        #[AutowireIterator(Guided::class)]
        private iterable $guides,
    ) {
    }

    /** @return array<string, array{name: string, description: TranslatableInterface, namespace: string, fields: list<Field>, guide: list<TranslatableInterface>}> */
    public function modules(): array
    {
        $fields = $this->settings->fields();
        $registry = Registry::installed();
        $namespaces = [];

        foreach ($this->bundles as $bundle) {
            if (is_subclass_of($bundle, Module::class)) {
                $namespaces[$bundle] = substr($bundle, 0, (int) strrpos($bundle, '\\'));
            }
        }

        $guides = [];
        foreach ($this->guides as $guided) {
            $owner = null;
            foreach ($namespaces as $bundle => $namespace) {
                if (str_starts_with($guided::class, $namespace.'\\') && (null === $owner || \strlen($namespace) > \strlen($namespaces[$owner]))) {
                    $owner = $bundle;
                }
            }

            if (null === $owner) {
                throw new \LogicException(\sprintf('guide %s lies outside of a module bundle namespace', $guided::class));
            }

            $guides[$owner] = $guided->guide();
        }

        $modules = [];
        foreach ($namespaces as $bundle => $namespace) {
            $name = Registry::key($bundle);
            $prefix = Registry::settings($bundle);
            $modules[strtolower($name)] = [
                'name' => $name,
                'description' => $registry->find($name)->description ?? new Description('', []),
                'namespace' => $namespace,
                'fields' => array_values(array_filter($fields, static fn (Field $field): bool => str_starts_with($field->name, $prefix))),
                'guide' => $guides[$bundle] ?? [],
            ];
        }

        return $modules;
    }

    public function schema(string $namespace): ?string
    {
        $prefix = $namespace.'\\Migrations\\';
        $applied = null;

        foreach ($this->migrations->getMetadataStorage()->getExecutedMigrations()->getItems() as $migration) {
            $version = (string) $migration->getVersion();

            if (str_starts_with($version, $prefix) && (null === $applied || $version > $applied)) {
                $applied = $version;
            }
        }

        return null === $applied ? null : substr($applied, \strlen($prefix));
    }
}
