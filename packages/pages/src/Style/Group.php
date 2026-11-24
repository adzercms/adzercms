<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Style;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Group: string implements Titled
{
    case Text = 'text';
    case Background = 'background';
    case Decoration = 'decoration';
    case Spacing = 'spacing';
    case Size = 'size';

    public function label(): TranslatableInterface
    {
        return t('pages.style.group.'.$this->value);
    }
}
