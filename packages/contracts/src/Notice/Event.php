<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Notice;

interface Event
{
    public function key(): string;

    public function template(): string;

    public function awaited(): bool;
}
