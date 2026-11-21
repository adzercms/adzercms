<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ProjectAccess: string implements Titled
{
    case Personal = 'personal';
    case Link = 'link';
    case Open = 'open';

    public function label(): TranslatableInterface
    {
        return t('builder.access.'.$this->value);
    }
}
