<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

final readonly class StoreDocument
{
    /** @param array<string, StoreEntry> $entries */
    public function __construct(public string $url, public ?string $etag, public array $entries)
    {
    }
}
