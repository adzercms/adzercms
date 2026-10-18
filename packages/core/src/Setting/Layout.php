<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Layout: string implements Titled
{
    case Side = 'side';
    case Top = 'top';

    public function label(): TranslatableInterface
    {
        return t('core.layout.'.$this->value);
    }
}
