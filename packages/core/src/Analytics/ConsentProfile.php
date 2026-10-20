<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Analytics;

use AdzerCMS\Contracts\Ui\Fragment;

final readonly class ConsentProfile implements Fragment
{
    public function __construct(private Counters $counters)
    {
    }

    public function place(): string
    {
        return self::CABINET_PROFILE;
    }

    public function fragment(?int $subject): ?array
    {
        return [] === $this->counters->active() ? null : ['template' => '@Core/consent/profile.html.twig', 'context' => []];
    }
}
