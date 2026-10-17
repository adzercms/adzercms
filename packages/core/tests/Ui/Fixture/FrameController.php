<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui\Fixture;

use AdzerCMS\Core\Account\Role;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[AsController]
final class FrameController
{
    #[Route('/fixture/frame', name: 'fixture_frame', methods: ['GET'])]
    #[IsGranted(Role::USER)]
    public function frame(Environment $twig): Response
    {
        return new Response($twig->render('@Theme/panel.html.twig'));
    }
}
