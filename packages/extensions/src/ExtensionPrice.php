<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ExtensionPrice: string implements Titled
{
    case Free = 'free';
    case Paid = 'paid';

    public function label(): TranslatableInterface
    {
        return t('extensions.'.$this->value);
    }
}
