<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Schedule;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Scheduled
{
    public function __construct(public string $cron)
    {
    }
}
