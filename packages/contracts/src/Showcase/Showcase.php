<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Showcase;

interface Showcase
{
    /** @return array<mixed> */
    public function section(): array;
}
