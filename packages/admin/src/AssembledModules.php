<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Core\Composer\Description;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Setting\Catalog;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class AssembledModules
{
    /** @param iterable<Guided> $guides */
    public function __construct(
        private Bundles $bundles,
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

        $guides = [];
        foreach ($this->guides as $guided) {
            $guides[$this->bundles->owner($guided)] = $guided->guide();
        }

        $modules = [];
        foreach ($this->bundles->namespaces() as $bundle => $namespace) {
            $name = Registry::key($bundle);
            $prefix = Registry::settings($bundle);
            $modules[Registry::lowerKey($bundle)] = [
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
