<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum PaymentMethod: string
{
    case Online = 'online';
    case OnReceipt = 'receipt';

    public function label(): TranslatableInterface
    {
        return t('shop.payment.'.$this->value);
    }
}
