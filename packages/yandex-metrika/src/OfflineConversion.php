<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika;

final readonly class OfflineConversion
{
    public function __construct(public string $click, public string $target, public \DateTimeImmutable $reachedAt)
    {
    }
}
