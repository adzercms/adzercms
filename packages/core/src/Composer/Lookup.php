<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Composer;

final readonly class Lookup
{
    /**
     * @param array<string, ?Package> $packages
     * @param list<string>            $down
     * @param array<string, string>   $sources
     */
    public function __construct(public array $packages, public array $down, public array $sources)
    {
    }

    public function decided(string $name): bool
    {
        return \array_key_exists($name, $this->packages);
    }
}
