<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Projects;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/builder/projects')]
#[IsGranted(Role::USER)]
final class ProjectController extends AbstractController
{
    public const LIST = 'builder_projects';
    public const PROJECT = 'builder_project';

    private const NEW = 'builder_project_new';
    private const ORDER = 'builder_project_order';
    private const PAGES = '@Builder/cabinet/_pages.html.twig';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectPageRepository $pages,
        private readonly Projects $work,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $table = Table::of($request, self::LIST, [
            Column::of(Projects::NAME, t('builder.project.name'), 'p.name'),
            Column::of('edited', t('builder.project.edited'), 'p.edited'),
        ], 'edited', t('builder.projects.empty'), ascending: false);
        $context = ['table' => $table, 'rows' => $this->projects->owned($user, $table)];

        return $this->render($request->headers->has('HX-Request') ? '@Builder/cabinet/_projects.html.twig' : '@Builder/cabinet/projects.html.twig', $context);
    }

    #[Route('/new', name: self::NEW, methods: ['GET', 'POST'])]
    public function create(#[CurrentUser] User $user, Request $request): Response
    {
        $refusals = [];
        if ($request->isMethod('POST')) {
            $outcome = $this->work->create($user, $request->request);
            if ($outcome instanceof Project) {
                return Fragment::go($this->generateUrl(self::PROJECT, ['id' => $outcome->getId()]));
            }
            $refusals = $outcome;
        }

        return $this->render('@Builder/cabinet/_new.html.twig', ['input' => $request->request->all(), 'refusals' => $refusals]);
    }

    #[Route('/{id}', name: self::PROJECT, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $project = $this->owned($user, $id);

        return $this->render($request->headers->has('HX-Request') ? self::PAGES : '@Builder/cabinet/project.html.twig', $this->listing($project, $request));
    }

    #[Route('/{id}/order', name: self::ORDER, requirements: ['id' => '\d+'], methods: ['POST'])]
    public function order(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $project = $this->owned($user, $id);
        $this->work->arrange($project, Order::moved($request));

        return $this->render(self::PAGES, $this->listing($project, $request));
    }

    #[Route('/{id}/pages/new', name: 'builder_project_page_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function add(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $project = $this->owned($user, $id);
        $refusals = [];
        if ($request->isMethod('POST')) {
            $outcome = $this->work->add($project, $request->request);
            if ($outcome instanceof ProjectPage) {
                $this->addFlash('success', t('pages.flash.added', ['title' => $outcome->getTitle()]));

                return Fragment::go($this->generateUrl(self::PROJECT, ['id' => $id]));
            }
            $refusals = $outcome;
        }

        return $this->render('@Builder/cabinet/_page.html.twig', ['project' => $project, 'input' => $request->request->all(), 'refusals' => $refusals]);
    }

    #[Route('/{id}/pages/{page}/drop', name: 'builder_project_page_drop', requirements: ['id' => '\d+', 'page' => '\d+'], methods: ['POST'])]
    public function dropPage(#[CurrentUser] User $user, int $id, int $page): Response
    {
        $one = $this->pages->findOneBy(['id' => $page, 'project' => $this->owned($user, $id)]) ?? throw $this->createNotFoundException();
        $this->work->drop($one);
        $this->addFlash('success', t('pages.flash.dropped', ['title' => $one->getTitle()]));

        return $this->redirectToRoute(self::PROJECT, ['id' => $id]);
    }

    #[Route('/{id}/drop', name: 'builder_project_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(#[CurrentUser] User $user, int $id): Response
    {
        $project = $this->owned($user, $id);
        $this->work->remove($project);
        $this->addFlash('success', t('builder.project.dropped', ['name' => $project->getName()]));

        return $this->redirectToRoute(self::LIST);
    }

    private function owned(User $user, int $id): Project
    {
        return $this->projects->findOneBy(['id' => $id, 'owner' => $user]) ?? throw $this->createNotFoundException();
    }

    /** @return array<string, mixed> */
    private function listing(Project $project, Request $request): array
    {
        $table = Table::of($request, self::PROJECT, [
            Column::plain(Projects::TITLE, t('pages.field.title')),
            Column::plain(Projects::PATH, t('pages.field.path')),
            Column::plain('actions'),
        ], Projects::TITLE, t('pages.empty'), sticky: ['id' => $project->getId()], order: Order::of(self::ORDER, 'p.position', 'p.id'));

        return ['project' => $project, 'table' => $table, 'rows' => $table->rows($this->pages->of($project))];
    }
}
