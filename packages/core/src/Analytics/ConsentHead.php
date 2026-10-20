<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Analytics;

use AdzerCMS\Contracts\Ui\Fragment;

final readonly class ConsentHead implements Fragment
{
    public function __construct(private Counters $counters)
    {
    }

    public function place(): string
    {
        return self::HEAD;
    }

    public function fragment(?int $subject): ?array
    {
        $counters = [];
        foreach ($this->counters->active() as [$counter, $credentials]) {
            $counters[] = ['name' => $counter->name(), 'entry' => $counter->entry(), 'settings' => $counter->settings($credentials), 'cookies' => $counter->cookies()];
        }

        return [] === $counters ? null : ['template' => '@Core/consent/head.html.twig', 'context' => ['counters' => $counters]];
    }
}
