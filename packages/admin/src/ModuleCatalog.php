<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Setting\Catalog;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class ModuleCatalog
{
    /** @param array<string, class-string> $bundles */
    public function __construct(
        #[Autowire('%kernel.bundles%')]
        private array $bundles,
        private Catalog $settings,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private DependencyFactory $migrations,
    ) {
    }

    /** @return array<string, array{name: string, kind: TranslatableInterface, namespace: string, fields: list<Field>}> */
    public function modules(): array
    {
        $fields = $this->settings->fields();
        $modules = [];

        foreach ($this->bundles as $bundle) {
            if (!is_subclass_of($bundle, Module::class)) {
                continue;
            }

            $name = Registry::key($bundle);
            $prefix = Registry::settings($bundle);
            $modules[strtolower($name)] = [
                'name' => $name,
                'kind' => $bundle::kind()->label(),
                'namespace' => substr($bundle, 0, (int) strrpos($bundle, '\\')),
                'fields' => array_values(array_filter($fields, static fn (Field $field): bool => str_starts_with($field->name, $prefix))),
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
