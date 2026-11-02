<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class Catalog
{
    /** @param iterable<Owner> $owners */
    public function __construct(
        #[AutowireIterator(Owner::class)]
        private iterable $owners,
        private Bundles $bundles,
    ) {
    }

    /** @return array<string, Field> */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->owned() as [$field]) {
            if (isset($fields[$field->name])) {
                throw new \LogicException(\sprintf('setting key %s is declared by two owners', $field->name));
            }
            $fields[$field->name] = $field;
        }

        return $fields;
    }

    /** @return array<string, non-empty-list<Field>> */
    public function system(): array
    {
        $pages = array_fill_keys(array_column(Page::cases(), 'value'), []);
        foreach ($this->owned() as [$field, $bundle]) {
            if (null === $bundle) {
                $pages[$field->page->value ?? throw new \LogicException(\sprintf('core setting key %s names no page', $field->name))][] = $field;
            }
        }

        return array_filter($pages);
    }

    /** @return array<string, array{bundle: class-string<Module>, fields: non-empty-list<Field>}> */
    public function modules(): array
    {
        $modules = [];
        foreach ($this->owned() as [$field, $bundle]) {
            if (null !== $bundle) {
                $key = Registry::lowerKey($bundle);
                $modules[$key] ??= ['bundle' => $bundle, 'fields' => []];
                $modules[$key]['fields'][] = $field;
            }
        }

        return $modules;
    }

    /** @return list<Field> */
    public function credential(string $group): array
    {
        return array_values(array_filter($this->fields(), static fn (Field $field): bool => $group === $field->group));
    }

    /** @return iterable<array{Field, class-string<Module>|null}> */
    private function owned(): iterable
    {
        $prefixes = [];
        foreach (array_keys($this->bundles->namespaces()) as $bundle) {
            $prefixes[$bundle] = Registry::settings($bundle);
        }

        foreach ($this->owners as $owner) {
            $module = $this->bundles->find($owner);
            foreach ($owner->settingFields() as $field) {
                $bundle = $module ?? (null === $field->page ? self::prefixed($prefixes, $field->name) : null);
                if (null !== $bundle && !str_starts_with($field->name, $prefixes[$bundle])) {
                    throw new \LogicException(\sprintf('setting key %s of module %s lacks its prefix %s', $field->name, Registry::key($bundle), $prefixes[$bundle]));
                }
                yield [$field, $bundle];
            }
        }
    }

    /**
     * @param array<class-string<Module>, string> $prefixes
     *
     * @return class-string<Module>|null
     */
    private static function prefixed(array $prefixes, string $name): ?string
    {
        $found = null;
        foreach ($prefixes as $bundle => $prefix) {
            if (str_starts_with($name, $prefix) && (null === $found || \strlen($prefix) > \strlen($prefixes[$found]))) {
                $found = $bundle;
            }
        }

        return $found;
    }
}
