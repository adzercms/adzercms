<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Builder\Repository\ProjectTransferRepository;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Entity\Page;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;

use function Symfony\Component\Translation\t;

final readonly class Projects
{
    public const PROJECTS = 20;
    public const PAGES = 50;
    public const PICTURES = 200;
    public const SIZE = 2;
    public const NAME = 'name';
    public const TITLE = 'title';
    public const PATH = 'path';
    public const ACCESS = 'access';

    public function __construct(
        private ProjectRepository $projects,
        private ProjectPageRepository $pages,
        private EntityManagerInterface $em,
        private ProjectAlbums $albums,
        private ProjectTransferRepository $transfers,
        private Transfers $handing,
    ) {
    }

    /**
     * @param InputBag<string> $input
     *
     * @return ProjectPage|array<string, Refusal>
     */
    public function create(User $owner, InputBag $input): ProjectPage|array
    {
        $name = trim($input->getString(self::NAME));
        $refusal = match (true) {
            '' === $name || mb_strlen($name) > Project::NAME => new Refusal(t('builder.refusal.name', ['limit' => Project::NAME])),
            default => $this->projects->overflow($owner),
        };
        if (null !== $refusal) {
            return [self::NAME => $refusal];
        }

        $project = new Project($owner, $name);
        $root = new ProjectPage($project, $name, Page::ROOT);
        $root->place(1);
        $this->em->persist($project);
        $this->em->persist($root);
        $this->em->flush();

        return $root;
    }

    public function copy(User $owner, ProjectVersion $version): Project|Refusal
    {
        $refusal = $this->projects->overflow($owner);
        if (null !== $refusal) {
            return $refusal;
        }

        $project = new Project($owner, $version->getName());
        $this->em->persist($project);
        $this->em->flush();
        $pages = $version->getPages();
        $copied = $this->albums->copy($this->albums->ofVersion($version), $this->albums->of($project), ProjectFile::names($pages));
        foreach (ProjectFile::renamed($pages, $copied) as $at => $one) {
            $page = new ProjectPage($project, $one['title'], $one['path']);
            $page->place($at + 1);
            $this->em->persist($page);
            foreach ($one['blocks'] as $position => $block) {
                $record = new ProjectBlock($page, $block['type'], $block['values']);
                $record->restyle($block['style']);
                $record->place($position + 1);
                $this->em->persist($record);
            }
        }
        $this->em->flush();

        return $project;
    }

    /**
     * @param InputBag<string> $input
     *
     * @return ProjectPage|array<string, Refusal>
     */
    public function add(Project $project, InputBag $input): ProjectPage|array
    {
        $pages = $this->pages->of($project);
        $title = trim($input->getString(self::TITLE));
        $path = Page::parse($input->getString(self::PATH));
        $holder = null === $path ? null : array_find($pages, static fn (ProjectPage $page): bool => $page->getPath() === $path);
        $refusals = array_filter([
            self::TITLE => match (true) {
                '' === $title || mb_strlen($title) > Page::TITLE => new Refusal(t('pages.refusal.title', ['limit' => Page::TITLE])),
                \count($pages) >= self::PAGES => new Refusal(t('builder.refusal.pages', ['limit' => self::PAGES])),
                default => null,
            },
            self::PATH => match (true) {
                null === $path => new Refusal(t('pages.refusal.path')),
                null !== $holder => new Refusal(t('pages.refusal.path_page', ['path' => $path, 'title' => $holder->getTitle()])),
                default => null,
            },
        ]);
        if ([] !== $refusals || null === $path) {
            return $refusals;
        }

        $page = new ProjectPage($project, $title, $path);
        $page->place(\count($pages) + 1);
        $project->touch();
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    public function grant(Project $project, ProjectAccess $access): void
    {
        $transfer = $this->transfers->pending($project);
        if (ProjectAccess::Personal !== $access && null !== $transfer) {
            $this->handing->cancel($transfer);
        }
        $project->setAccess($access);
        $this->em->flush();
    }

    /** @param list<int> $moved */
    public function arrange(Project $project, array $moved): void
    {
        Order::place($this->pages->of($project), $moved);
        $project->touch();
        $this->em->flush();
    }

    public function drop(ProjectPage $page): void
    {
        $page->getProject()->touch();
        $this->em->remove($page);
        $this->em->flush();
    }

    public function remove(Project $project): void
    {
        $albums = $this->albums->all($project);
        $this->em->remove($project);
        $this->em->flush();
        foreach ($albums as $album) {
            $album->clear();
        }
    }
}
