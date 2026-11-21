<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Builder\ProjectAlbums;
use AdzerCMS\Builder\ProjectFile;
use AdzerCMS\Builder\Projects;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Builder\Repository\ProjectTransferRepository;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Builder\Transfers;
use AdzerCMS\Builder\Versions;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    public const PICTURE = 'builder_project_picture';

    private const NEW = 'builder_project_new';
    private const ORDER = 'builder_project_order';
    private const PAGES = '@Builder/cabinet/_pages.html.twig';
    private const ZIP = 'application/zip';
    private const EXTENSION = '.zip';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectPageRepository $pages,
        private readonly Projects $work,
        private readonly ProjectAlbums $albums,
        private readonly ProjectFile $file,
        private readonly ProjectTransferRepository $transfers,
        private readonly Transfers $handing,
        private readonly ProjectVersionRepository $versions,
        private readonly Versions $versioning,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $table = Table::of($request, self::LIST, [
            Column::of(Projects::NAME, t('builder.project.name'), 'p.name'),
            Column::of(Projects::ACCESS, t('builder.access.column'), 'p.access'),
            Column::of('version', t('builder.version.column'), ProjectRepository::DECISION),
            Column::of('edited', t('builder.project.edited'), 'p.edited'),
        ], 'edited', t('builder.projects.empty'), ascending: false);
        $rows = $this->projects->owned($user, $table);
        $context = ['table' => $table, 'rows' => $rows, 'versions' => $this->versions->latest($rows)];

        return $request->headers->has('HX-Request')
            ? $this->render('@Builder/cabinet/_projects.html.twig', $context)
            : $this->render('@Builder/cabinet/projects.html.twig', $context + ['offers' => $this->transfers->offered($user)]);
    }

    #[Route('/new', name: self::NEW, methods: ['GET', 'POST'])]
    public function create(#[CurrentUser] User $user, Request $request): Response
    {
        $refusals = [];
        if ($request->isMethod('POST')) {
            $outcome = $this->work->create($user, $request->request);
            if ($outcome instanceof ProjectPage) {
                return Fragment::go($this->generateUrl(EditorController::PROJECT, ['project' => $outcome->getProject()->getId(), 'id' => $outcome->getId()]));
            }
            $refusals = $outcome;
        }

        return $this->render('@Builder/cabinet/_new.html.twig', ['input' => $request->request->all(), 'refusals' => $refusals]);
    }

    #[Route('/{id}', name: self::PROJECT, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $project = $this->owned($user, $id);

        return $request->headers->has('HX-Request')
            ? $this->render(self::PAGES, $this->listing($project, $request))
            : $this->render('@Builder/cabinet/project.html.twig', $this->listing($project, $request) + ['transfer' => $this->transfers->pending($project), 'version' => $this->versions->latest([$project])[$id] ?? null, 'shown' => null !== $this->versions->shown($project)]);
    }

    #[Route('/{id}/access', name: 'builder_project_access', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function access(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $project = $this->owned($user, $id);
        if ($request->isMethod('POST')) {
            $this->work->grant($project, ProjectAccess::tryFrom($request->request->getString(Projects::ACCESS)) ?? throw new BadRequestHttpException());
            $this->addFlash('success', t('builder.access.granted', ['name' => $project->getName(), 'access' => $project->getAccess()->label()]));

            return Fragment::go($this->generateUrl(self::PROJECT, ['id' => $id]));
        }

        return $this->render('@Builder/cabinet/_access.html.twig', ['project' => $project, 'accesses' => ProjectAccess::cases()]);
    }

    #[Route('/{id}/version', name: 'builder_project_version', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function submit(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $project = $this->owned($user, $id);
        if (ProjectAccess::Personal === $project->getAccess()) {
            throw $this->createNotFoundException();
        }
        $refusals = [];
        if ($request->isMethod('POST')) {
            $cover = $request->files->get(Versions::COVER);
            $outcome = $this->versioning->submit($project, $request->request->getString(Versions::NAME), $cover instanceof UploadedFile ? $cover : null);
            if ($outcome instanceof ProjectVersion) {
                $this->addFlash('success', t('builder.version.submitted', ['name' => $outcome->getName()]));

                return Fragment::go($this->generateUrl(self::PROJECT, ['id' => $id]));
            }
            $refusals = $outcome;
        }

        return $this->render('@Builder/cabinet/_version.html.twig', ['project' => $project, 'input' => $request->request->all(), 'refusals' => $refusals]);
    }

    #[Route('/{id}/transfer', name: 'builder_project_transfer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function transfer(#[CurrentUser] User $user, int $id, Request $request): Response
    {
        $project = $this->owned($user, $id);
        $refusals = [];
        if ($request->isMethod('POST')) {
            $refusal = $this->handing->offer($project, $request->request->getString(Transfers::EMAIL));
            if (null === $refusal) {
                $this->addFlash('success', t('builder.transfer.offered', ['name' => $project->getName()]));

                return Fragment::go($this->generateUrl(self::PROJECT, ['id' => $id]));
            }
            $refusals = [Transfers::EMAIL => $refusal];
        }

        return $this->render('@Builder/cabinet/_transfer.html.twig', ['project' => $project, 'input' => $request->request->all(), 'refusals' => $refusals]);
    }

    #[Route('/{id}/transfer/cancel', name: 'builder_project_transfer_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(#[CurrentUser] User $user, int $id): Response
    {
        $project = $this->owned($user, $id);
        $this->handing->cancel($this->transfers->pending($project) ?? throw $this->createNotFoundException());
        $this->addFlash('success', t('builder.transfer.withdrawn', ['name' => $project->getName()]));

        return $this->redirectToRoute(self::PROJECT, ['id' => $id]);
    }

    #[Route('/transfers/{id}/accept', name: 'builder_transfer_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(#[CurrentUser] User $user, int $id): Response
    {
        $transfer = $this->transfers->received($user, $id) ?? throw $this->createNotFoundException();
        $refusal = $this->handing->accept($transfer);
        if (null !== $refusal) {
            $this->addFlash('error', $refusal->reason);

            return $this->redirectToRoute(self::LIST);
        }
        $this->addFlash('success', t('builder.transfer.taken', ['name' => $transfer->getProject()->getName()]));

        return $this->redirectToRoute(self::PROJECT, ['id' => $transfer->getProject()->getId()]);
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

    #[Route('/{id}/pictures/{file}', name: self::PICTURE, requirements: ['id' => '\d+', 'file' => '[^/]+'], methods: ['GET'])]
    public function picture(#[CurrentUser] User $user, int $id, string $file): Response
    {
        return new BinaryFileResponse($this->albums->of($this->owned($user, $id))->path($file) ?? throw $this->createNotFoundException(), autoEtag: true);
    }

    #[Route('/{id}/file', name: 'builder_project_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(#[CurrentUser] User $user, int $id): Response
    {
        $project = $this->owned($user, $id);
        $response = new BinaryFileResponse($this->file->write($project), headers: ['Content-Type' => self::ZIP]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, strtr($project->getName(), '/\\', '--').self::EXTENSION, 'project-'.$id.self::EXTENSION);

        return $response->deleteFileAfterSend();
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
