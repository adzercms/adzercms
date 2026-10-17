<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

final readonly class Answer
{
    /** @param array<array-key, mixed> $fields */
    public function __construct(private array $fields)
    {
    }

    public function text(string ...$path): ?string
    {
        $value = $this->fields;
        foreach ($path as $key) {
            $value = \is_array($value) ? ($value[$key] ?? null) : null;
        }

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }
}
