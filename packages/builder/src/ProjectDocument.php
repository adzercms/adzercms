<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Controller\EditorController;
use AdzerCMS\Builder\Controller\ProjectController;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\Album;
use AdzerCMS\Pages\Entity\BlockRecord;
use AdzerCMS\Pages\PhotoAlbum;
use AdzerCMS\Pages\Site;

use function Symfony\Component\Translation\t;

final readonly class ProjectDocument implements Document
{
    private const MEGABYTE = 1048576;

    public function __construct(
        private ProjectPage $page,
        private ProjectBlockRepository $blocks,
        private PhotoAlbum $album,
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
        return new ProjectBlock($this->page, $type);
    }

    public function album(): Album
    {
        return $this->album;
    }

    public function sizeRefusal(array $blocks): ?Refusal
    {
        $size = array_sum(array_map(static fn (array $block): int => self::size($block['values'], $block['style']), $this->blocks->others($this->page)))
            + array_sum(array_map(static fn (array $block): int => self::size(...$block), $blocks));

        return $size > Projects::SIZE * self::MEGABYTE ? new Refusal(t('builder.refusal.size', ['megabytes' => Projects::SIZE])) : null;
    }

    public function picturesRefusal(int $added): ?Refusal
    {
        return \count($this->album->listed()) + $added > Projects::PICTURES ? new Refusal(t('builder.refusal.pictures', ['limit' => Projects::PICTURES])) : null;
    }

    public function touch(): void
    {
        $this->page->getProject()->touch();
    }

    public function view(): array
    {
        return $this->site->sheet($this->page->getTitle(), $this->blocks(), $this->album);
    }

    public function route(): array
    {
        return [EditorController::PROJECT, ['project' => (int) $this->page->getProject()->getId(), 'id' => (int) $this->page->getId()]];
    }

    public function exit(): array
    {
        return [ProjectController::PROJECT, ['id' => (int) $this->page->getProject()->getId()]];
    }

    public function picture(): array
    {
        return [ProjectController::PICTURE, ['id' => (int) $this->page->getProject()->getId()]];
    }

    /**
     * @param array<string, string>                               $values
     * @param array<string, array<string, array<string, string>>> $style
     */
    private static function size(array $values, array $style): int
    {
        return \strlen(json_encode($values, \JSON_THROW_ON_ERROR)) + \strlen(json_encode($style, \JSON_THROW_ON_ERROR));
    }
}
