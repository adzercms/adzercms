<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Analytics;

final readonly class Report
{
    /** @param list<Visits> $steps */
    public function __construct(public Visits $total, public array $steps, public \DateTimeImmutable $since)
    {
    }
}
