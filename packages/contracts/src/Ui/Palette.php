<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface Palette
{
    /** @return non-empty-list<Scheme> */
    public function schemes(): array;
}
