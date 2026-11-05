<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Controller\Admin;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Pages\BlockRows;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\PageForm;
use AdzerCMS\Pages\Repository\PageRepository;
use AdzerCMS\Pages\Site;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/pages')]
#[IsGranted(Role::ADMIN)]
final class SiteController extends AbstractController
{
    public const LIST = 'admin_pages';
    public const PAGE = 'admin_page';

    private const ORDER = 'admin_pages_order';
    private const ROWS = '@Pages/admin/_pages.html.twig';

    public function __construct(
        private readonly PageRepository $pages,
        private readonly PageForm $form,
        private readonly BlockRows $blocks,
        private readonly Site $site,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render($request->headers->has('HX-Request') ? self::ROWS : '@Pages/admin/pages.html.twig', $this->listing($request));
    }

    #[Route('/order', name: self::ORDER, methods: ['POST'])]
    public function order(Request $request): Response
    {
        $this->form->arrange(Order::moved($request));

        return $this->render(self::ROWS, $this->listing($request));
    }

    #[Route('/new', name: 'admin_page_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $refusals = [];
        if ($request->isMethod('POST')) {
            $outcome = $this->form->add($request->request);
            if ($outcome instanceof Page) {
                $this->addFlash('success', t('pages.flash.added', ['title' => $outcome->getTitle()]));

                return Fragment::go($this->generateUrl(self::PAGE, ['id' => $outcome->getId()]));
            }
            $refusals = $outcome;
        }

        return $this->render('@Pages/admin/_new.html.twig', ['input' => $request->request->all(), 'refusals' => $refusals]);
    }

    #[Route('/{id}', name: self::PAGE, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Page $page, Request $request): Response
    {
        return $this->card($page, $request, [], null);
    }

    #[Route('/{id}', name: 'admin_page_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(Page $page, Request $request): Response
    {
        $refusals = $this->form->save($page, $request->request);
        if ([] !== $refusals) {
            return $this->card($page, $request, $refusals, $request->request->all());
        }
        $this->addFlash('success', t('pages.flash.saved', ['title' => $page->getTitle()]));

        return $this->redirectToRoute(self::PAGE, ['id' => $page->getId()]);
    }

    #[Route('/{id}/drop', name: 'admin_page_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(Page $page): Response
    {
        $title = $page->getTitle();
        $this->form->drop($page);
        $this->addFlash('success', t('pages.flash.dropped', ['title' => $title]));

        return $this->redirectToRoute(self::LIST);
    }

    /** @return array<string, mixed> */
    private function listing(Request $request): array
    {
        $table = Table::of($request, self::LIST, [
            Column::plain(PageForm::TITLE, t('pages.field.title')),
            Column::plain(PageForm::PATH, t('pages.field.path')),
            Column::plain(PageForm::PUBLISHED, t('pages.field.published')),
            Column::plain(PageForm::MENU, t('pages.field.menu')),
        ], PageForm::TITLE, t('pages.empty'), order: Order::of(self::ORDER, 'p.position', 'p.id'));

        return ['table' => $table, 'rows' => $table->rows($this->pages->ordered())];
    }

    /**
     * @param array<string, Refusal>    $refusals
     * @param array<string, mixed>|null $input
     */
    private function card(Page $page, Request $request, array $refusals, ?array $input): Response
    {
        return $this->render('@Pages/admin/page.html.twig', [
            'page' => $page,
            'open' => $page->isPublished() ? $this->site->url($page) : $this->generateUrl(PageController::PREVIEW, ['id' => $page->getId()]),
            'refusals' => $refusals,
            'input' => $input,
            'blocks' => $this->blocks->listing($page, $request),
        ], new Response(status: [] === $refusals ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
