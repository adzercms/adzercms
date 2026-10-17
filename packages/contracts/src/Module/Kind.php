<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

use AdzerCMS\Contracts\Ui\Titled;

interface Kind extends Titled
{
    public function key(): string;

    public function environment(): string;

    public function single(): bool;
}
