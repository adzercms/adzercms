<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

final readonly class Credentials
{
    /** @param array<string, string> $values */
    public function __construct(private array $values)
    {
    }

    public function get(string $field): string
    {
        return $this->values[$field] ?? '';
    }
}
