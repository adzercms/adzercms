<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Notice\Event;

enum ExtensionEvent: string implements Event
{
    case Decision = 'extensions.event.decision';

    public function key(): string
    {
        return $this->value;
    }

    public function template(): string
    {
        return '@Extensions/notice/decision';
    }

    public function awaited(): bool
    {
        return false;
    }
}
