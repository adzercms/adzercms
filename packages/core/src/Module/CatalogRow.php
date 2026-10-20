<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Core\Composer\Package;

final readonly class CatalogRow
{
    public function __construct(
        public string $name,
        public Package $package,
        public Kind|string $kind,
        public ?string $version,
        public ?string $installed,
        public ?string $repository,
    ) {
    }
}
