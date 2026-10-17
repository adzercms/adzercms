<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum PeriodScale: string implements Titled
{
    case Month = 'month';
    case Year = 'year';
    case Custom = 'custom';

    public function label(): TranslatableInterface
    {
        return t('core.period_scale.'.$this->value);
    }
}
