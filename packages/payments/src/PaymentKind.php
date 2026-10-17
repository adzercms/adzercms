<?php

declare(strict_types=1);

namespace AdzerCMS\Payments;

use AdzerCMS\Contracts\Module\Kind;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum PaymentKind: string implements Kind
{
    case Acquiring = 'PAYMENT_MODULE';

    public function key(): string
    {
        return 'payment';
    }

    public function label(): TranslatableInterface
    {
        return t('payments.kind');
    }

    public function environment(): string
    {
        return $this->value;
    }

    public function single(): bool
    {
        return true;
    }
}
