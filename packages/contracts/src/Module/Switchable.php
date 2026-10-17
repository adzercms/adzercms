<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

use AdzerCMS\Contracts\Setting\Field;

interface Switchable
{
    /** @return list<Field> */
    public function credentials(): array;
}
