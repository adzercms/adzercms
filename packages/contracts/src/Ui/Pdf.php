<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface Pdf
{
    public function file(string $html): string;
}
