<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Controller\EditorController;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\Album;
use AdzerCMS\Pages\Controller\Admin\SiteController;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Entity\BlockRecord;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Pictures;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Site;

final readonly class SiteDocument implements Document
{
    public function __construct(
        private Page $page,
        private PageBlockRepository $blocks,
        private Pictures $pictures,
        private Site $site,
    ) {
    }

    public function title(): string
    {
        return $this->page->getTitle();
    }

    public function blocks(): array
    {
        return $this->blocks->of($this->page);
    }

    public function block(string $type): BlockRecord
    {
        return new PageBlock($this->page, $type);
    }

    public function album(): Album
    {
        return $this->pictures;
    }

    public function sizeRefusal(array $blocks): ?Refusal
    {
        return null;
    }

    public function picturesRefusal(int $added): ?Refusal
    {
        return null;
    }

    public function touch(): void
    {
    }

    public function view(): array
    {
        return $this->site->view($this->page);
    }

    public function menu(): ?array
    {
        return null;
    }

    public function route(): array
    {
        return [EditorController::EDITOR, ['id' => (int) $this->page->getId()]];
    }

    public function exit(): array
    {
        return [SiteController::PAGE, ['id' => (int) $this->page->getId()]];
    }

    public function picture(): array
    {
        return [PageController::PICTURE, []];
    }
}
