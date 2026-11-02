<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Core\Ui\Entries;
use AdzerCMS\Core\Ui\Menus;
use Twig\Attribute\AsTwigFunction;

final readonly class SectionSettings
{
    public function __construct(
        private Menus $menus,
        private Catalog $catalog,
        private Entries $entries,
    ) {
    }

    #[AsTwigFunction('section_settings')]
    public function url(): ?string
    {
        foreach (array_merge(...array_column($this->menus->of(Area::Admin->value), 'items')) as $entry) {
            if ($entry['active']) {
                return null !== $entry['module'] && isset($this->catalog->modules()[$entry['module']])
                    ? $this->entries->url(AdminEntry::ModuleSettings, ['key' => $entry['module']])
                    : null;
            }
        }

        return null;
    }
}
