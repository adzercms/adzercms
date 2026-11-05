<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\Translation\t;

final readonly class PageForm
{
    public const TITLE = 'title';
    public const PATH = 'path';
    public const DESCRIPTION = 'description';
    public const PUBLISHED = 'published';
    public const MENU = 'menu';
    public const MENU_LABEL = 'menu_label';

    public function __construct(
        private PageRepository $pages,
        private RouterInterface $router,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param InputBag<string> $input
     *
     * @return Page|array<string, Refusal>
     */
    public function add(InputBag $input): Page|array
    {
        $title = self::title($input);
        $path = $this->path($input, null);
        if ($title instanceof Refusal || $path instanceof Refusal) {
            return self::refusals([self::TITLE => $title, self::PATH => $path]);
        }

        $page = new Page($title, $path);
        $page->place($this->pages->count() + 1);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * @param InputBag<string> $input
     *
     * @return array<string, Refusal>
     */
    public function save(Page $page, InputBag $input): array
    {
        $title = self::title($input);
        $path = $this->path($input, $page);
        $label = trim($input->getString(self::MENU_LABEL));
        $long = mb_strlen($label) > Page::MENU ? new Refusal(t('pages.refusal.menu_label', ['limit' => Page::MENU])) : null;
        if ($title instanceof Refusal || $path instanceof Refusal || null !== $long) {
            return self::refusals([self::TITLE => $title, self::PATH => $path, self::MENU_LABEL => $long]);
        }

        $description = trim($input->getString(self::DESCRIPTION));
        $page->edit(
            $title,
            $path,
            '' === $description ? null : $description,
            $input->getBoolean(self::PUBLISHED),
            $input->getBoolean(self::MENU),
            '' === $label ? null : $label,
        );
        $this->em->flush();

        return [];
    }

    public function drop(Page $page): void
    {
        $this->em->remove($page);
        $this->em->flush();
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
    private static function title(InputBag $input): string|Refusal
    {
        $title = trim($input->getString(self::TITLE));

        return '' === $title || mb_strlen($title) > Page::TITLE ? new Refusal(t('pages.refusal.title', ['limit' => Page::TITLE])) : $title;
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

    private function routed(string $path): bool
    {
        if (Page::ROOT === $path) {
            return false;
        }

        $context = clone $this->router->getContext();
        $context->setMethod(Request::METHOD_GET);
        try {
            return PageController::PAGE !== (new UrlMatcher($this->router->getRouteCollection(), $context))->match($path)['_route'];
        } catch (ResourceNotFoundException) {
            return false;
        } catch (MethodNotAllowedException) {
            return true;
        }
    }
}
