<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Module\Switchable;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function Symfony\Component\Translation\t;

final class Switches implements Owner
{
    public const ENABLED = 'enabled';

    /** @param iterable<Switchable> $modules */
    public function __construct(
        #[AutowireIterator(Switchable::class)]
        private readonly iterable $modules,
        private readonly Bundles $bundles,
        private readonly Settings $settings,
    ) {
    }

    public function settingFields(): array
    {
        $fields = [];
        foreach ($this->keyed() as [$prefix, $module]) {
            $fields[] = new Field($prefix.self::ENABLED, t('core.setting.enabled'), Type::Toggle);
            foreach ($module->credentials() as $field) {
                $fields[] = $this->prefixed($prefix, $field);
            }
        }

        return $fields;
    }

    /**
     * @template T of Switchable
     *
     * @param class-string<T> $contract
     *
     * @return array{T, Credentials}|null
     */
    public function first(string $contract): ?array
    {
        return array_values($this->enabled($contract))[0] ?? null;
    }

    /**
     * @template T of Switchable
     *
     * @param class-string<T> $contract
     *
     * @return array<string, array{T, Credentials}>
     */
    public function enabled(string $contract): array
    {
        $enabled = [];
        foreach ($this->keyed() as $key => [$prefix, $module]) {
            if (!$module instanceof $contract || !$this->settings->on($prefix.self::ENABLED)) {
                continue;
            }

            $values = [];
            foreach ($module->credentials() as $field) {
                $values[$field->name] = $this->settings->get($prefix.$field->name);
                if ($field->required && '' === $values[$field->name]) {
                    continue 2;
                }
            }

            $enabled[$key] = [$module, new Credentials($values)];
        }

        return $enabled;
    }

    public function setting(Switchable $module, Field $field): Field
    {
        return $this->prefixed($this->owner($module)[1], $field);
    }

    public function key(Switchable $module): string
    {
        return $this->owner($module)[0];
    }

    /** @return array{string, string} */
    private function owner(Switchable $module): array
    {
        foreach ($this->keyed() as $key => [$prefix, $owner]) {
            if ($owner === $module) {
                return [$key, $prefix];
            }
        }

        throw new \LogicException(\sprintf('switchable %s belongs to no selected module', $module::class));
    }

    private function prefixed(string $prefix, Field $field): Field
    {
        return new Field(
            $prefix.$field->name,
            $field->label,
            $field->type,
            $field->required,
            $field->choices,
            null === $field->group ? null : $prefix.$field->group,
            $field->section,
        );
    }

    /** @return array<string, array{string, Switchable}> */
    private function keyed(): array
    {
        $keyed = [];
        foreach (array_keys($this->bundles->namespaces()) as $bundle) {
            $keyed[Registry::lowerKey($bundle)] = null;
        }
        foreach ($this->modules as $module) {
            $bundle = $this->bundles->owner($module);
            $key = Registry::lowerKey($bundle);
            if (null !== $keyed[$key]) {
                throw new \LogicException(\sprintf('module %s has two switchables: %s and %s', $key, $keyed[$key][1]::class, $module::class));
            }
            $keyed[$key] = [Registry::settings($bundle), $module];
        }

        return array_filter($keyed);
    }
}
