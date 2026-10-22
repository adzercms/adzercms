<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Ui\Fragment;

final readonly class LeadWindow implements Fragment
{
    public function place(): string
    {
        return self::END;
    }

    public function fragment(?int $subject): array
    {
        return ['template' => '@Leads/_window.html.twig', 'context' => []];
    }
}
