<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

final readonly class Selection
{
    /** @param list<Installed> $modules */
    private function __construct(public array $modules)
    {
    }

    /** @param \Closure(string): string $environment */
    public static function of(Registry $registry, \Closure $environment): self
    {
        $chosen = [];
        foreach ($registry->kinds() as $kind) {
            $keys = array_values(array_unique(array_filter(
                array_map(trim(...), explode(',', $environment($kind->environment()))),
                static fn (string $key): bool => '' !== $key,
            )));

            if ($kind->single() && \count($keys) > 1) {
                throw new \LogicException(sprintf('%s selects one module, selected: %s', $kind->environment(), implode(', ', $keys)));
            }

            foreach ($keys as $key) {
                $module = $registry->find($key);
                if (null === $module || $module->kind !== $kind) {
                    $available = array_map(static fn (Installed $module): string => $module->key, $registry->ofKind($kind));

                    throw new \LogicException(sprintf('module "%s" in %s is not installed, available: %s', $key, $kind->environment(), [] === $available ? 'none' : implode(', ', $available)));
                }
                $chosen[$key] = $module;
            }
        }

        $ordered = [];
        $seen = [];
        foreach ($chosen as $module) {
            self::place($module, $chosen, $registry, $seen, $ordered);
        }

        return new self($ordered);
    }

    public function id(): string
    {
        return [] === $this->modules
            ? ''
            : substr(hash('xxh128', implode(',', array_map(static fn (Installed $module): string => $module->key, $this->modules))), 0, 16);
    }

    /**
     * @param array<string, Installed> $chosen
     * @param array<string, true>      $seen
     * @param list<Installed>          $ordered
     */
    private static function place(Installed $module, array $chosen, Registry $registry, array &$seen, array &$ordered): void
    {
        if (isset($seen[$module->key])) {
            return;
        }
        $seen[$module->key] = true;

        foreach ($module->requires as $package) {
            $required = $registry->package($package) ?? throw new \LogicException(sprintf('module package %s is not installed', $package));
            if (!isset($chosen[$required->key])) {
                throw new \LogicException(sprintf('module "%s" requires module "%s" selected in %s', $module->key, $required->key, $required->kind->environment()));
            }
            self::place($required, $chosen, $registry, $seen, $ordered);
        }

        foreach ($registry->required($module) as $kind) {
            $providers = array_filter($chosen, static fn (Installed $chosen): bool => $chosen->kind === $kind);
            if ([] === $providers) {
                throw new \LogicException(sprintf('module "%s" requires a module selected in %s', $module->key, $kind->environment()));
            }
            foreach ($providers as $provider) {
                self::place($provider, $chosen, $registry, $seen, $ordered);
            }
        }

        $ordered[] = $module;
    }
}
