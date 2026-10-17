<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Refusal
{
    /** @param array<string, string|int> $params */
    public function __construct(public TranslatableInterface $reason, public ?string $route = null, public array $params = [])
    {
    }
}
