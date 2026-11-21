<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Site\Hosts;
use AdzerCMS\Core\Site\SiteWorker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class WorkerController
{
    private const TYPE = 'text/javascript; charset=UTF-8';

    public function __construct(private Environment $twig)
    {
    }

    #[Route('/worker.js', name: SiteWorker::ROUTE, defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response($this->twig->render(SiteWorker::SCRIPT), headers: ['Content-Type' => self::TYPE]);
    }
}
