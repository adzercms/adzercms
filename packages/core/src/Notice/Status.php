<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Status: string implements Badged
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Deferred = 'deferred';

    public function label(): TranslatableInterface
    {
        return t('core.notice.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Deferred => 'warning',
        };
    }
}
