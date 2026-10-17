<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;

interface Titled
{
    public function label(): TranslatableInterface;
}
