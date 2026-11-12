<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Security\ModuleVoter;
use AdzerCMS\Core\Setting\Catalog;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
        private AuthorizationCheckerInterface $authorization,
        #[AutowireIterator(Guided::class)]
        private iterable $guides,
    ) {
    }

    /** @return array<string, non-empty-list<Field>> */
    public function system(): array
    {
        $system = $this->catalog->system();
        if (!$this->authorization->isGranted(ModuleVoter::MANAGE)) {
            unset($system[ModuleSources::PAGE->value]);
        }

        return $system;
    }

    /** @return list<array{label: TranslatableInterface, href: string, fields: list<Field>, page: ?Page, module: ?string}> */
    public function pages(): array
    {
        $pages = [];
        foreach ($this->system() as $key => $fields) {
            $pages[] = ['label' => Page::from($key)->label(), 'href' => $this->urls->generate('admin_settings', ['page' => $key]), 'fields' => $fields, 'page' => Page::from($key), 'module' => null];
        }
        $assembled = $this->assembled->modules();
        foreach ($this->catalog->modules() as $key => $fields) {
            $pages[] = ['label' => $assembled[$key]['title'], 'href' => $this->urls->generate(self::MODULE_ROUTE, ['key' => $key]), 'fields' => $fields, 'page' => null, 'module' => $key];
        }

        return $pages;
    }

    /** @return list<Group> */
    public function menu(?Page $page, ?string $module): array
    {
        $assembled = $this->assembled->modules();
        $system = [];
        $features = [];
        $kinds = [];
        foreach ($this->pages() as $entry) {
            $item = ['label' => $entry['label'], 'href' => $entry['href'], 'active' => null === $entry['module'] ? $entry['page'] === $page : $entry['module'] === $module];
            if (null === $entry['module']) {
                $system[] = $item;

                continue;
            }
            $kind = $assembled[$entry['module']]['kind'];
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
