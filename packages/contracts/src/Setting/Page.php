<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Page: string implements Titled
{
    case General = 'general';
    case Brand = 'brand';
    case Mail = 'mail';
    case Extensions = 'extensions';

    public function label(): TranslatableInterface
    {
        return t('core.setting.page.'.$this->value);
    }
}
