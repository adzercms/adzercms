<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Receipt: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    public function label(): TranslatableInterface
    {
        return t('shop.receipt.'.$this->value);
    }
}
