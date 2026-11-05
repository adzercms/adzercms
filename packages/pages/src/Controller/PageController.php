<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Controller;

use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Entries;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageRepository;
use AdzerCMS\Pages\Site;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PageController extends AbstractController
{
    public const ROOT = 'pages_root';
    public const PAGE = 'pages_page';
    public const PREVIEW = 'admin_page_preview';
    public const LAST = \PHP_INT_MIN;

    private const UNINDEXED = 'noindex, nofollow';

    public function __construct(
        private readonly PageRepository $pages,
        private readonly Site $site,
    ) {
    }

    #[Route('/', name: self::ROOT, methods: ['GET'], options: ['site' => true])]
    public function root(Entries $entries): Response
    {
        $page = $this->pages->published(Page::ROOT);
        if (null !== $page) {
            return $this->show($page);
        }

        return $this->moved(Page::ROOT) ?? $this->redirect($entries->url(CabinetEntry::Home) ?? throw $this->createNotFoundException());
    }

    #[Route('/{path}', name: self::PAGE, requirements: ['path' => Page::SEGMENTS], methods: ['GET'], options: ['site' => true, 'utf8' => true], priority: self::LAST)]
    public function page(string $path): Response
    {
        $page = $this->pages->published('/'.$path);
        if (null !== $page) {
            return $this->show($page);
        }

        return $this->moved('/'.$path) ?? throw $this->createNotFoundException();
    }

    #[Route('/admin/pages/{id}/preview', name: self::PREVIEW, requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(Role::ADMIN)]
    public function preview(Page $page): Response
    {
        $response = $this->show($page);
        $response->headers->set('X-Robots-Tag', self::UNINDEXED);

        return $response;
    }

    private function show(Page $page): Response
    {
        return $this->render('@Pages/page.html.twig', ['page' => $page, 'view' => $this->site->view($page)]);
    }

    private function moved(string $path): ?Response
    {
        $page = $this->pages->moved($path);

        return null === $page ? null : $this->redirect($this->site->url($page), Response::HTTP_MOVED_PERMANENTLY);
    }
}
