<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Pages\Controller\Admin\SiteController;

final readonly class PageAction implements Fragment
{
    public function place(): string
    {
        return SiteController::ACTIONS;
    }

    public function fragment(?int $subject): ?array
    {
        return null === $subject ? null : ['template' => '@Builder/_action.html.twig', 'context' => ['page' => $subject]];
    }
}
