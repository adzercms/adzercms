<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Controller;

use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Ui\Entries;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageRepository;
use AdzerCMS\Pages\Site;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    public const ROOT = 'pages_root';
    public const PAGE = 'pages_page';
    public const LAST = \PHP_INT_MIN;

    public function __construct(
        private readonly PageRepository $pages,
        private readonly Site $site,
    ) {
    }

    #[Route('/', name: self::ROOT, methods: ['GET'], options: ['site' => true])]
    public function root(Entries $entries): Response
    {
        $page = $this->pages->published(Page::ROOT);
        if (null === $page) {
            return $this->redirect($entries->url(CabinetEntry::Home) ?? throw $this->createNotFoundException());
        }

        return $this->show($page);
    }

    #[Route('/{path}', name: self::PAGE, requirements: ['path' => Page::SEGMENTS], methods: ['GET'], options: ['site' => true, 'utf8' => true], priority: self::LAST)]
    public function page(string $path): Response
    {
        return $this->show($this->pages->published('/'.$path) ?? throw $this->createNotFoundException());
    }

    private function show(Page $page): Response
    {
        return $this->render('@Pages/page.html.twig', ['page' => $page, 'view' => $this->site->view($page)]);
    }
}
