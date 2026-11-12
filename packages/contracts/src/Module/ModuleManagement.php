<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

interface ModuleManagement
{
    public function allowed(): bool;
}
