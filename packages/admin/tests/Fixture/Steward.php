<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests\Fixture;

use AdzerCMS\Contracts\Module\ModuleManagement;

final class Steward implements ModuleManagement
{
    public bool $open = true;

    public function allowed(): bool
    {
        return $this->open;
    }
}
