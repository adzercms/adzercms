<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ModuleAction: string implements Titled
{
    case Install = 'install';
    case Update = 'update';
    case Enable = 'enable';
    case Disable = 'disable';
    case Remove = 'remove';

    public function label(): TranslatableInterface
    {
        return t('core.module.action.'.$this->value);
    }

    public function takesVersion(): bool
    {
        return self::Install === $this || self::Update === $this;
    }
}
