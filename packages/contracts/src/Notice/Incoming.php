<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Notice;

final readonly class Incoming
{
    public function __construct(public string $text, public string $sender, public ?string $account)
    {
    }
}
