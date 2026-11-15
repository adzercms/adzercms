<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Tests\Fixture;

use AdzerCMS\Shop\PickupPoint;
use AdzerCMS\Shop\PickupPoints;

final class Counters implements PickupPoints
{
    /** @var list<PickupPoint> */
    public array $points = [];

    public function points(): array
    {
        return $this->points;
    }
}
