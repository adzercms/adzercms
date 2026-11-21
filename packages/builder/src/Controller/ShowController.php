<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\Projects;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Builder\VersionShow;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Seo\Unindexed;
use AdzerCMS\Core\Site\Hosts;
use AdzerCMS\Pages\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use function Symfony\Component\Translation\t;

final class ShowController extends AbstractController
{
    public const SHOW = 'builder_show';
    public const COPY = 'builder_show_copy';

    private const TOKEN = '[0-9a-f]{'.Project::TOKEN.'}';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectVersionRepository $versions,
        private readonly VersionShow $show,
        private readonly Projects $work,
    ) {
    }

    #[Route('/templates/{token}/{path}', name: self::SHOW, requirements: ['token' => self::TOKEN, VersionShow::PATH => Page::SEGMENTS], defaults: [VersionShow::PATH => '', Hosts::ROLE => Hosts::SITE, Unindexed::ROUTE => true], methods: ['GET'], options: ['utf8' => true])]
    public function show(string $token, string $path, Request $request, #[CurrentUser] ?User $user): Response
    {
        $version = $this->shown($token);

        return $this->render('@Builder/show.html.twig', [
            'view' => $this->show->view($request, $version, $path, self::SHOW, ['token' => $token]),
            'version' => $version,
            'copy' => $user?->getId() !== $version->getProject()->getOwner()->getId(),
        ]);
    }

    #[Route('/templates/copy/{token}', name: self::COPY, requirements: ['token' => self::TOKEN], defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET', 'POST'])]
    public function copy(string $token, Request $request, #[CurrentUser] ?User $user): Response
    {
        $version = $this->shown($token);
        $session = $request->getSession();
        if (null === $user) {
            if ($request->isMethod('POST')) {
                $session->set(self::COPY, $token);

                return $this->redirectToRoute(self::COPY, ['token' => $token]);
            }

            throw $this->createAccessDeniedException();
        }
        if ($user->getId() === $version->getProject()->getOwner()->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$request->isMethod('POST') && $token !== $session->remove(self::COPY)) {
            return $this->redirectToRoute(self::SHOW, ['token' => $token]);
        }

        $copy = $this->work->copy($user, $version);
        if ($copy instanceof Refusal) {
            $this->addFlash('error', $copy->reason);

            return $this->redirectToRoute(self::SHOW, ['token' => $token]);
        }
        $this->addFlash('success', t('builder.project.copied', ['name' => $copy->getName()]));

        return $this->redirectToRoute(ProjectController::PROJECT, ['id' => $copy->getId()]);
    }

    private function shown(string $token): ProjectVersion
    {
        $project = $this->projects->findOneBy(['token' => $token]);

        return (null === $project ? null : $this->versions->shown($project)) ?? throw $this->createNotFoundException();
    }
}
