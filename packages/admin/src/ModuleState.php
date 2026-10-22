<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Core\Module\CatalogRow;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ModuleState: string implements Badged
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Available = 'available';

    public static function of(CatalogRow $row): self
    {
        return match (true) {
            null === $row->installed => self::Available,
            $row->selected => self::Enabled,
            default => self::Disabled,
        };
    }

    public function label(): TranslatableInterface
    {
        return t('admin.modules.state.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Enabled => 'success',
            self::Disabled => 'secondary',
            self::Available => 'info',
        };
    }
}
