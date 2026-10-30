<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Analytics;

final readonly class Visits
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $last,
        public int $visitors,
        public int $visits,
        public int $views,
    ) {
    }
}
