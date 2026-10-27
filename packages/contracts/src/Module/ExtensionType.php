<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

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
        return t('core.extension_type.'.$this->value);
    }

    public function tab(): TranslatableInterface
    {
        return t('core.extension_tab.'.$this->value);
    }

    /** @return array<string, TranslatableInterface> */
    public static function tabs(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(static fn (self $type): TranslatableInterface => $type->tab(), self::cases()));
    }
}
