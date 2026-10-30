<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Analytics;

use AdzerCMS\Contracts\Refusal;

interface Traffic
{
    public function available(): bool;

    public function report(\DateTimeImmutable $from, \DateTimeImmutable $last, Step $step): Report|Refusal;
}
