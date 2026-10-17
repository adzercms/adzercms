<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

use AdzerCMS\Contracts\Refusal;

interface Rule
{
    public function check(Field $field, string $value): ?Refusal;
}
