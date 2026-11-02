<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum State: string implements Badged
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Archived = 'archived';
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Online = 'online';
    case Offline = 'offline';

    public function label(): TranslatableInterface
    {
        return t('core.state.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active, self::Done, self::Online => 'success',
            self::Pending => 'warning',
            self::Running => 'info',
            self::Failed => 'danger',
            self::Disabled, self::Archived, self::Skipped, self::Offline => 'secondary',
        };
    }
}
