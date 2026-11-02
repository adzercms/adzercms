<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Core\Ui\State;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Status: string implements Badged
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Deferred = 'deferred';

    public function label(): TranslatableInterface
    {
        return self::Failed === $this ? State::Failed->label() : t('core.notice.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Failed => State::Failed->badge(),
            self::Deferred => 'warning',
        };
    }
}
