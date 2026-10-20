<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Seo;

interface Pages
{
    /** @return list<Page> */
    public function pages(): array;
}
