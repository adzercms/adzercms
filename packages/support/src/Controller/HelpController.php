<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Controller;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Support\Help\Help;
use AdzerCMS\Support\Repository\TicketRepository;
use AdzerCMS\Support\SupportSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/help')]
#[IsGranted(Role::USER)]
final class HelpController extends AbstractController
{
    public function __construct(
        private readonly Help $help,
        private readonly SupportSettings $support,
        private readonly TicketRepository $tickets,
    ) {
    }

    #[Route('/window', name: 'help_window', methods: ['GET'])]
    public function dialog(): Response
    {
        $this->open();

        return $this->render('@Support/support/_help_window.html.twig');
    }

    #[Route('', name: 'help', methods: ['GET'])]
    public function window(Request $request): Response
    {
        $this->open();

        return $this->render('@Support/support/_help.html.twig', [
            'answer' => $this->help->window(
                (string) $request->query->get('q'),
                (string) parse_url(Back::here($request), \PHP_URL_PATH),
            ),
        ]);
    }

    #[Route('/ticket/{id}', name: 'help_ticket', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function ticket(#[CurrentUser] User $user, Request $request, int $id): Response
    {
        $this->open();

        if (Role::Admin !== $user->getRole()) {
            throw $this->createAccessDeniedException();
        }

        $ticket = $this->tickets->find($id) ?? throw $this->createNotFoundException();

        return $this->render('@Support/support/_help_ticket.html.twig', [
            'links' => $this->help->reply((string) $request->query->get('q'), $ticket->getSubject()),
        ]);
    }

    private function open(): void
    {
        if (!$this->support->helpEnabled()) {
            throw $this->createNotFoundException();
        }
    }
}
