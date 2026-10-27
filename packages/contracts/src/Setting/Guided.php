<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

use Symfony\Contracts\Translation\TranslatableInterface;

interface Guided
{
    /** @return non-empty-list<TranslatableInterface> */
    public function guide(): array;
}
