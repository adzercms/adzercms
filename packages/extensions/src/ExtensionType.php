<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ExtensionType: string implements Titled
{
    case Module = 'module';
    case Integration = 'integration';
    case Theme = 'theme';
    case Template = 'template';
    case App = 'app';

    public function label(): TranslatableInterface
    {
        return t('extensions.type.'.$this->value);
    }
}
