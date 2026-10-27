<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\ExtensionType;

final readonly class StoreEntry
{
    public function __construct(public ?ExtensionType $type, public ?string $demo)
    {
    }
}
