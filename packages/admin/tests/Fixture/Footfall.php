<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests\Fixture;

use AdzerCMS\Contracts\Analytics\Report;
use AdzerCMS\Contracts\Analytics\Step;
use AdzerCMS\Contracts\Analytics\Traffic;
use AdzerCMS\Contracts\Analytics\Visits;
use AdzerCMS\Contracts\Refusal;

use function Symfony\Component\Clock\now;

final class Footfall implements Traffic
{
    public const TOTAL = [7, 9, 20];

    public bool $open = false;

    public ?Refusal $refusal = null;

    /** @var list<Visits> */
    public array $steps = [];

    public \DateTimeImmutable $since;

    /** @var list<array{\DateTimeImmutable, \DateTimeImmutable, Step}> */
    public array $asked = [];

    public function __construct()
    {
        $this->since = now()->setTime(0, 0);
    }

    public function available(): bool
    {
        return $this->open;
    }

    public function report(\DateTimeImmutable $from, \DateTimeImmutable $last, Step $step): Report|Refusal
    {
        $this->asked[] = [$from, $last, $step];

        return $this->refusal ?? new Report(new Visits($from, $last, ...self::TOTAL), $this->steps, $this->since);
    }
}
