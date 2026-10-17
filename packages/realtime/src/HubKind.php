<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use AdzerCMS\Contracts\Module\Kind;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum HubKind: string implements Kind
{
    case Hub = 'HUB_MODULE';

    public function key(): string
    {
        return 'hub';
    }

    public function label(): TranslatableInterface
    {
        return t('realtime.kind');
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
