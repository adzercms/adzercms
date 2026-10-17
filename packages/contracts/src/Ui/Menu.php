<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface Menu
{
    /** @return list<MenuItem> */
    public function items(Area $area): array;
}
