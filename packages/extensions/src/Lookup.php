<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

final readonly class Lookup
{
    /**
     * @param array<string, ?Package> $packages
     * @param list<string>            $down
     */
    public function __construct(public array $packages, public array $down)
    {
    }

    public function decided(string $name): bool
    {
        return \array_key_exists($name, $this->packages);
    }
}
