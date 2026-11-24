<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Site\Hosts;
use AdzerCMS\Core\Text\TextValues;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PagePath;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Repository\PagePathRepository;
use AdzerCMS\Pages\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\Translation\t;

final readonly class PageForm
{
    public const TITLE = 'title';
    public const PATH = 'path';
    public const FORMER = 'former';
    public const DESCRIPTION = 'description';
    public const PUBLISHED = 'published';
    public const MENU_LABEL = 'menu_label';

    public function __construct(
        private PageRepository $pages,
        private PagePathRepository $paths,
        private PageBlockRepository $blocks,
        private Pictures $pictures,
        private RouterInterface $router,
        private Hosts $hosts,
        private EntityManagerInterface $em,
        private TextValues $values,
    ) {
    }

    /**
     * @param InputBag<string> $input
     *
     * @return Page|array<string, Refusal>
     */
    public function add(InputBag $input): Page|array
    {
        $title = $this->title($input->getString(self::TITLE));
        $path = $this->path($input, null);
        if ($title instanceof Refusal || $path instanceof Refusal) {
            return self::refusals([self::TITLE => $title, self::PATH => $path]);
        }

        [$page] = $this->append([[$title, $path]]);
        $this->em->flush();

        return $page;
    }

    /**
     * @param list<array{string, string}> $pages
     *
     * @return list<Page>
     */
    public function append(array $pages): array
    {
        $count = $this->pages->count();
        $added = [];
        foreach ($pages as [$title, $path]) {
            $page = new Page($title, $path);
            $page->place(++$count);
            $this->em->persist($page);
            $this->release($path);
            $added[] = $page;
        }

        return $added;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public function taken(array $paths): array
    {
        $held = array_map(static fn (Page $page): string => $page->getPath(), $this->pages->holding($paths));

        return array_values(array_filter($paths, fn (string $path): bool => \in_array($path, $held, true) || $this->routed($path)));
    }

    /**
     * @param InputBag<string> $input
     *
     * @return array<string, Refusal>
     */
    public function save(Page $page, InputBag $input): array
    {
        $title = $this->title($input->getString(self::TITLE));
        $path = $this->path($input, $page);
        $former = $path instanceof Refusal ? [] : $this->former($input, $page, $path);
        $description = trim($input->getString(self::DESCRIPTION));
        $unknown = $this->values->refusal($description, t('pages.field.description'));
        $label = trim($input->getString(self::MENU_LABEL));
        $long = mb_strlen($label) > Page::MENU ? new Refusal(t('pages.refusal.menu_label', ['limit' => Page::MENU])) : null;
        if ($title instanceof Refusal || $path instanceof Refusal || $former instanceof Refusal || null !== $unknown || null !== $long) {
            return self::refusals([self::TITLE => $title, self::PATH => $path, self::FORMER => $former instanceof Refusal ? $former : null, self::DESCRIPTION => $unknown, self::MENU_LABEL => $long]);
        }

        if ($path !== $page->getPath()) {
            $former = array_values(array_unique([...$former, $page->getPath()]));
            $this->release($path);
        }
        $this->keep($page, $former);
        $page->edit(
            $title,
            $path,
            '' === $description ? null : $description,
            $input->getBoolean(self::PUBLISHED),
            '' === $label ? null : $label,
        );
        $this->em->flush();

        return [];
    }

    public function title(string $input): string|Refusal
    {
        $title = trim($input);
        if ('' === $title || mb_strlen($title) > Page::TITLE) {
            return new Refusal(t('pages.refusal.title', ['limit' => Page::TITLE]));
        }

        return $this->values->refusal($title, t('pages.field.title')) ?? $title;
    }

    public function lines(Page $page): string
    {
        return implode("\n", array_map(static fn (PagePath $former): string => $former->getPath(), $this->paths->of($page)));
    }

    public function drop(Page $page): void
    {
        $names = PhotoAlbum::records($this->blocks->of($page));
        $this->em->remove($page);
        $this->em->flush();
        $this->pictures->drop($names);
    }

    /** @param list<int> $moved */
    public function arrange(array $moved): void
    {
        Order::place($this->pages->ordered(), $moved);
        $this->em->flush();
    }

    /**
     * @param array<string, string|Refusal|null> $fields
     *
     * @return array<string, Refusal>
     */
    private static function refusals(array $fields): array
    {
        return array_filter($fields, static fn (string|Refusal|null $field): bool => $field instanceof Refusal);
    }

    /** @param InputBag<string> $input */
    private function path(InputBag $input, ?Page $page): string|Refusal
    {
        $path = Page::parse($input->getString(self::PATH));
        if (null === $path) {
            return new Refusal(t('pages.refusal.path'));
        }

        $holder = $this->pages->findOneBy(['path' => $path]);
        if (null !== $holder && $holder !== $page) {
            return new Refusal(t('pages.refusal.path_page', ['path' => $path, 'title' => $holder->getTitle()]));
        }

        return $this->routed($path) ? new Refusal(t('pages.refusal.path_route', ['path' => $path])) : $path;
    }

    /**
     * @param InputBag<string> $input
     *
     * @return list<string>|Refusal
     */
    private function former(InputBag $input, Page $page, string $path): array|Refusal
    {
        $former = [];
        foreach (preg_split('/\R/u', $input->getString(self::FORMER)) ?: [] as $line) {
            $line = trim($line);
            $parsed = '' === $line ? $path : Page::parse($line, Page::FORMER);
            if (null === $parsed) {
                return new Refusal(t('pages.refusal.former', ['path' => $line]));
            }
            if ($parsed !== $path) {
                $former[$parsed] = $parsed;
            }
        }
        $former = array_values($former);

        foreach ($this->pages->holding($former) as $holder) {
            if ($holder !== $page) {
                return new Refusal(t('pages.refusal.path_page', ['path' => $holder->getPath(), 'title' => $holder->getTitle()]));
            }
        }
        foreach ($this->paths->held($former) as $held) {
            if ($held->getPage() !== $page) {
                return new Refusal(t('pages.refusal.path_former', ['path' => $held->getPath(), 'title' => $held->getPage()->getTitle()]));
            }
        }
        $routed = array_find($former, $this->routed(...));

        return null === $routed ? $former : new Refusal(t('pages.refusal.path_route', ['path' => $routed]));
    }

    /** @param list<string> $paths */
    private function keep(Page $page, array $paths): void
    {
        foreach ($this->paths->of($page) as $kept) {
            if (\in_array($kept->getPath(), $paths, true)) {
                $paths = array_values(array_diff($paths, [$kept->getPath()]));
            } else {
                $this->em->remove($kept);
            }
        }
        foreach ($paths as $path) {
            $this->em->persist(new PagePath($page, $path));
        }
    }

    private function release(string $path): void
    {
        foreach ($this->paths->held([$path]) as $former) {
            $this->em->remove($former);
        }
    }

    private function routed(string $path): bool
    {
        if (Page::ROOT === $path) {
            return false;
        }

        $own = $this->router->getContext();
        $site = $this->hosts->target(Hosts::SITE, $own) ?? clone $own;
        try {
            return PageController::PAGE !== Hosts::match($this->router, $site->setMethod(Request::METHOD_GET), $path)['_route'];
        } catch (ResourceNotFoundException) {
            return false;
        } catch (MethodNotAllowedException) {
            return true;
        }
    }
}
