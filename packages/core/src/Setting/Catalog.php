<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Core\Module\Registry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final class Catalog
{
    /**
     * @param iterable<Owner>             $owners
     * @param array<string, class-string> $bundles
     */
    public function __construct(
        #[AutowireIterator(Owner::class)]
        private readonly iterable $owners,
        #[Autowire('%kernel.bundles%')]
        private readonly array $bundles,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @return array<string, Field> */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->owners as $owner) {
            foreach ($owner->settingFields() as $field) {
                if (isset($fields[$field->name])) {
                    throw new \LogicException(\sprintf('setting key %s is declared by two owners', $field->name));
                }
                $fields[$field->name] = $field;
            }
        }

        return $fields;
    }

    /** @return array<string, array{label: TranslatableInterface, fields: list<Field>}> */
    public function groups(): array
    {
        $cards = [];
        foreach ($this->bundles as $bundle) {
            if (is_subclass_of($bundle, Module::class)) {
                $cards[] = Registry::settings($bundle);
            }
        }

        $groups = [];
        foreach ($this->owners as $owner) {
            foreach ($owner->settingFields() as $field) {
                if (array_any($cards, static fn (string $card): bool => str_starts_with($field->name, $card))) {
                    continue;
                }
                $area = strstr($field->name, '.', true) ?: $field->name;
                $key = $field->tab->value ?? $area;
                $groups[$key] ??= ['label' => $field->tab?->label() ?? t($area.'.setting.area'), 'fields' => []];
                $groups[$key]['fields'][] = $field;
            }
        }

        $rank = array_flip(array_column(Tab::cases(), 'value'));
        $collator = new \Collator($this->translator->getLocale());
        $title = fn (string $key): string => $groups[$key]['label']->trans($this->translator);
        uksort($groups, static fn (string $one, string $other): int => ($rank[$one] ?? \count($rank)) <=> ($rank[$other] ?? \count($rank))
            ?: (int) $collator->compare($title($one), $title($other)));

        return $groups;
    }

    /** @return list<Field> */
    public function credential(string $group): array
    {
        return array_values(array_filter($this->fields(), static fn (Field $field): bool => $group === $field->group));
    }
}
