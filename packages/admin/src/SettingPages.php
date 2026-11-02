<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Setting\Catalog;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @phpstan-type Item array{label: TranslatableInterface, href: string, active: bool}
 * @phpstan-type Group array{label: TranslatableInterface, subgroups: list<array{label: ?TranslatableInterface, items: list<Item>}>}
 */
final readonly class SettingPages
{
    public const MODULE_ROUTE = 'admin_settings_module';

    /** @param iterable<Guided> $guides */
    public function __construct(
        private Catalog $catalog,
        private AssembledModules $assembled,
        private Bundles $bundles,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        #[AutowireIterator(Guided::class)]
        private iterable $guides,
    ) {
    }

    /** @return list<Group> */
    public function menu(?Page $page, ?string $module): array
    {
        $system = [];
        foreach (array_keys($this->catalog->system()) as $key) {
            $case = Page::from($key);
            $system[] = ['label' => $case->label(), 'href' => $this->urls->generate('admin_settings', ['page' => $key]), 'active' => $case === $page];
        }

        $assembled = $this->assembled->modules();
        $features = [];
        $kinds = [];
        foreach (array_keys($this->catalog->modules()) as $key) {
            $item = ['label' => $assembled[$key]['title'], 'href' => $this->urls->generate(self::MODULE_ROUTE, ['key' => $key]), 'active' => $key === $module];
            $kind = $assembled[$key]['kind'];
            if (CoreKind::Feature === $kind) {
                $features[] = $item;
            } else {
                $kinds[$kind->key()]['label'] = $kind->label();
                $kinds[$kind->key()]['items'][] = $item;
            }
        }

        $kinds = array_values($kinds);
        usort($kinds, $this->byLabel(...));

        return array_values(array_filter([
            ['label' => t('admin.settings.system'), 'subgroups' => [['label' => null, 'items' => $system]]],
            ['label' => t('admin.settings.modules'), 'subgroups' => [['label' => null, 'items' => $this->sorted($features)]]],
            ['label' => t('admin.settings.integrations'), 'subgroups' => array_map(fn (array $kind): array => ['label' => $kind['label'], 'items' => $this->sorted($kind['items'])], $kinds)],
        ], static fn (array $group): bool => array_any($group['subgroups'], static fn (array $subgroup): bool => [] !== $subgroup['items'])));
    }

    public function title(string $module): ?TranslatableInterface
    {
        return $this->assembled->modules()[$module]['title'] ?? null;
    }

    /** @return list<TranslatableInterface> */
    public function guide(string $module): array
    {
        foreach ($this->guides as $guided) {
            if (Registry::lowerKey($this->bundles->owner($guided)) === $module) {
                return $guided->guide();
            }
        }

        return [];
    }

    /**
     * @param list<Item> $items
     *
     * @return list<Item>
     */
    private function sorted(array $items): array
    {
        usort($items, $this->byLabel(...));

        return $items;
    }

    /**
     * @param array{label: TranslatableInterface} $one
     * @param array{label: TranslatableInterface} $other
     */
    private function byLabel(array $one, array $other): int
    {
        $collator = new \Collator($this->translator->getLocale());

        return (int) $collator->compare($one['label']->trans($this->translator), $other['label']->trans($this->translator));
    }
}
