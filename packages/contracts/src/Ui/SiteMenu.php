<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface SiteMenu
{
    /**
     * @param array<string, SiteLink> $sections
     *
     * @return list<SiteLink>
     */
    public function arrange(array $sections): array;
}
