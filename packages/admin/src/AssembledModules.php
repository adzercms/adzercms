<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Core\Composer\Localized;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Setting\Catalog;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AssembledModules
{
    public function __construct(
        private Bundles $bundles,
        private Catalog $settings,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private DependencyFactory $migrations,
    ) {
    }

    /** @return array<string, array{name: string, title: Localized, kind: Kind, description: Localized, namespace: string, settings: bool}> */
    public function modules(): array
    {
        $configured = $this->settings->modules();
        $registry = Registry::installed();

        $modules = [];
        foreach ($this->bundles->namespaces() as $bundle => $namespace) {
            $name = Registry::key($bundle);
            $installed = $registry->find($name);
            $key = Registry::lowerKey($bundle);
            $modules[$key] = [
                'name' => $name,
                'title' => null === $installed || '' === $installed->title->source ? new Localized($name, []) : $installed->title,
                'kind' => $bundle::kind(),
                'description' => $installed->description ?? new Localized('', []),
                'namespace' => $namespace,
                'settings' => isset($configured[$key]),
            ];
        }

        return $modules;
    }

    public function schema(string $namespace): ?\DateTimeImmutable
    {
        $prefix = $namespace.'\\Migrations\\Version';
        $applied = null;

        foreach ($this->migrations->getMetadataStorage()->getExecutedMigrations()->getItems() as $migration) {
            $version = (string) $migration->getVersion();

            if (str_starts_with($version, $prefix) && (null === $applied || $version > $applied)) {
                $applied = $version;
            }
        }

        return null === $applied ? null : (\DateTimeImmutable::createFromFormat('!YmdHis', substr($applied, \strlen($prefix))) ?: null);
    }
}
