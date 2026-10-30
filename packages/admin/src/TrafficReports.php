<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Contracts\Analytics\Traffic;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class TrafficReports
{
    /** @param iterable<Traffic> $reports */
    public function __construct(
        #[AutowireIterator(Traffic::class)]
        private iterable $reports,
    ) {
    }

    public function available(): ?Traffic
    {
        foreach ($this->reports as $report) {
            if ($report->available()) {
                return $report;
            }
        }

        return null;
    }
}
