<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ConsentKind: string implements Titled
{
    case Offer = 'offer';
    case Personal = 'personal';

    public function label(): TranslatableInterface
    {
        return t('core.consent_kind.'.$this->value);
    }
}
