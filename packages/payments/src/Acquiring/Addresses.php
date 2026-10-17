<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

final readonly class Addresses
{
    public function __construct(public string $callback, public string $return)
    {
    }
}
