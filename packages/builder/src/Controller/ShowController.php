<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Builder\VersionShow;
use AdzerCMS\Core\Seo\Unindexed;
use AdzerCMS\Core\Site\Hosts;
use AdzerCMS\Pages\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowController extends AbstractController
{
    public const SHOW = 'builder_show';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectVersionRepository $versions,
        private readonly VersionShow $show,
    ) {
    }

    #[Route('/templates/{token}/{path}', name: self::SHOW, requirements: ['token' => '[0-9a-f]{'.Project::TOKEN.'}', VersionShow::PATH => Page::SEGMENTS], defaults: [VersionShow::PATH => '', Hosts::ROLE => Hosts::SITE, Unindexed::ROUTE => true], methods: ['GET'], options: ['utf8' => true])]
    public function show(string $token, string $path, Request $request): Response
    {
        $project = $this->projects->findOneBy(['token' => $token]);
        $version = null === $project ? null : $this->versions->shown($project);

        return $this->render(VersionShow::TEMPLATE, ['view' => $this->show->view($request, $version ?? throw $this->createNotFoundException(), $path, self::SHOW, ['token' => $token])]);
    }
}
