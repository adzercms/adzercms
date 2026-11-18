<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Contracts\Ui\SiteSections;
use AdzerCMS\Support\Controller\KnowledgeBaseController;

use function Symfony\Component\Translation\t;

final readonly class KnowledgeNav implements SiteSections
{
    private const WEIGHT = 200;

    public function __construct(private Help $help)
    {
    }

    public function sections(): array
    {
        return $this->help->enabled()
            ? [new MenuItem(KnowledgeBaseController::CONTENTS, t('support.help.title'), self::WEIGHT, [KnowledgeBaseController::CATEGORY, KnowledgeBaseController::ARTICLE])]
            : [];
    }
}
