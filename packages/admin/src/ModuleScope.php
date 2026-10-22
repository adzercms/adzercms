<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Ui\Titled;
use AdzerCMS\Core\Module\CatalogRow;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ModuleScope: string implements Titled
{
    case Installed = 'installed';
    case Available = 'available';
    case Updatable = 'updatable';

    public function label(): TranslatableInterface
    {
        return t('admin.modules.scope.'.$this->value);
    }

    public function admits(CatalogRow $row): bool
    {
        return match ($this) {
            self::Installed => null !== $row->installed,
            self::Available => null === $row->installed,
            self::Updatable => $row->updatable,
        };
    }
}
