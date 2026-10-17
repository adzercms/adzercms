<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface Badged extends Titled
{
    public function badge(): string;
}
