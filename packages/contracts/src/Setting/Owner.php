<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

interface Owner
{
    /** @return list<Field> */
    public function settingFields(): array;
}
