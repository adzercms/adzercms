<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Pages\Controller\Admin\SiteController;

final readonly class UploadAction implements Fragment
{
    public function place(): string
    {
        return SiteController::TOOLS;
    }

    public function fragment(?int $subject): array
    {
        return ['template' => '@Builder/_upload.html.twig', 'context' => []];
    }
}
