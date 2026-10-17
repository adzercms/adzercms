<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum HelpView: string implements Titled
{
    case Found = 'found';
    case Page = 'page';
    case Categories = 'categories';
    case Missing = 'missing';

    public function label(): TranslatableInterface
    {
        return t('support.help_view.'.$this->value);
    }
}
