<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\Module;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Bundles
{
    /** @var array<class-string<Module>, string> */
    private array $namespaces;

    /** @param array<string, class-string> $bundles */
    public function __construct(#[Autowire('%kernel.bundles%')] array $bundles)
    {
        $namespaces = [];
        foreach ($bundles as $bundle) {
            if (is_subclass_of($bundle, Module::class)) {
                $namespaces[$bundle] = substr($bundle, 0, (int) strrpos($bundle, '\\'));
            }
        }
        $this->namespaces = $namespaces;
    }

    /** @return array<class-string<Module>, string> */
    public function namespaces(): array
    {
        return $this->namespaces;
    }

    /** @return class-string<Module> */
    public function owner(object $service): string
    {
        $owner = null;
        foreach ($this->namespaces as $bundle => $namespace) {
            if (str_starts_with($service::class, $namespace.'\\') && (null === $owner || \strlen($namespace) > \strlen($this->namespaces[$owner]))) {
                $owner = $bundle;
            }
        }

        return $owner ?? throw new \LogicException(\sprintf('%s lies outside of a module bundle namespace', $service::class));
    }
}
