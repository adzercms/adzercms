<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
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

    public function __construct(
        private ProjectRepository $projects,
        private ProjectPageRepository $pages,
        private EntityManagerInterface $em,
        private ProjectAlbums $albums,
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
            $this->projects->counted($owner) >= self::PROJECTS => new Refusal(t('builder.refusal.projects', ['limit' => self::PROJECTS])),
            default => null,
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
        $album = $this->albums->of($project);
        $this->em->remove($project);
        $this->em->flush();
        $album->clear();
    }
}
