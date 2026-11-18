<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface SiteSections
{
    /** @return list<MenuItem> */
    public function sections(): array;
}
