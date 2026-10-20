<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum RequestState: string implements Badged
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): TranslatableInterface
    {
        return t('core.module.state.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Running => 'warning',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }

    public function open(): bool
    {
        return self::Pending === $this || self::Running === $this;
    }
}
