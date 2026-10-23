<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface PickupPoints
{
    /** @return list<PickupPoint> */
    public function points(): array;
}
