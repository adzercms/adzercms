<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\ExtensionType;

final readonly class StoreEntry
{
    /** @param array<string, ?string> $images copy of each image address, none until taken */
    public function __construct(public ?ExtensionType $type, public ?string $demo, public array $images = [])
    {
    }
}
