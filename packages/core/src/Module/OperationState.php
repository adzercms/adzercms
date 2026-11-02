<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Core\Ui\State;
use Symfony\Contracts\Translation\TranslatableInterface;

enum OperationState: string implements Badged
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): TranslatableInterface
    {
        return $this->state()->label();
    }

    public function badge(): string
    {
        return $this->state()->badge();
    }

    public function open(): bool
    {
        return self::Pending === $this || self::Running === $this;
    }

    private function state(): State
    {
        return match ($this) {
            self::Pending => State::Pending,
            self::Running => State::Running,
            self::Done => State::Done,
            self::Failed => State::Failed,
        };
    }
}
