<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Controller;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Attachment\Attachments;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\Entity\TicketAttachment;
use AdzerCMS\Support\Repository\TicketAttachmentRepository;
use AdzerCMS\Support\Repository\TicketMessageRepository;
use AdzerCMS\Support\Repository\TicketRepository;
use AdzerCMS\Support\Subjects;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tickets;
use AdzerCMS\Support\TicketStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/support')]
#[IsGranted(Role::USER)]
final class SupportController extends AbstractController
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketMessageRepository $messages,
        private readonly TicketAttachmentRepository $attachments,
        private readonly Attachments $files,
        private readonly Subjects $subjects,
        private readonly SupportSettings $support,
        private readonly Tickets $service,
    ) {
    }

    #[Route('', name: 'support_tickets', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $staff = $this->staff($user);
        $owner = $this->owner($user);
        $archive = 'archive' === $request->query->get('tab');

        $departments = $this->tickets->departments($owner);

        $table = Table::of($request, 'support_tickets', array_values(array_filter([
            Column::of('number', t('support.column.number'), 't.id')->aside(),
            $staff ? Column::of('user', t('support.column.user'), 's.id') : null,
            Column::of('subject', t('support.column.subject'), 't.subject'),
            Column::of('department', t('support.column.department'), 't.department')->aside(),
            Column::of('at', t('support.column.at'), 't.lastMessageAt'),
            Column::of('status', t('support.column.status'), 't.status'),
        ])), 'at', t('support.empty'), false,
            filters: array_filter([
                Filter::search(),
                Filter::select('department', t('support.column.department'), array_combine($departments, $departments))->on('t.department'),
                $archive ? null : Filter::cases('status', t('support.filter.status'), TicketStatus::active())->on('t.status'),
            ]),
            sticky: $archive ? ['tab' => 'archive'] : [],
        );

        $context = [
            'staff' => $staff,
            'archive' => $archive,
            'table' => $table,
            'rows' => $this->tickets->page($owner, $archive, $table),
            'counts' => $this->tickets->counts($owner),
        ];

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Support/support/_tickets.html.twig', $context);
        }

        return $this->render('@Support/support/tickets.html.twig', $context);
    }

    #[Route('/new', name: 'support_ticket_new', methods: ['GET', 'POST'])]
    public function create(#[CurrentUser] User $user, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $outcome = $this->subjects->check($user, $request) ?? $this->service->open(
                $user,
                $request->request->getString('subject'),
                $request->request->getString('department'),
                $request->request->getString('body'),
                $request->files->all('files'),
            );

            if ($outcome instanceof Ticket) {
                $this->subjects->attach($outcome, $request);
                $this->addFlash('success', t('support.flash.opened', ['number' => $outcome->getId()]));

                return $this->redirectToRoute('support_ticket', ['id' => $outcome->getId()]);
            }

            $this->addFlash('error', $outcome->reason);
        }

        return $this->render('@Support/support/new.html.twig', [
            'departments' => $this->support->departments(),
            'subjects' => $this->subjects->fields($user, $request),
            'allowance' => $this->support->attachments(),
            'department' => (string) $request->request->get('department'),
            'subject' => (string) $request->request->get('subject'),
            'body' => (string) $request->request->get('body'),
        ]);
    }

    #[Route('/{id}', name: 'support_ticket', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[CurrentUser] User $user, int $id): Response
    {
        $ticket = $this->found($this->tickets->conversation($id, $this->owner($user)));
        if (!$this->staff($user) && !$this->isGranted(AuthenticatedVoter::IS_IMPERSONATOR)) {
            $this->service->read($ticket);
        }

        return $this->render('@Support/support/ticket.html.twig', [
            'ticket' => $ticket,
            'subjects' => $this->subjects->links($ticket, $user),
            'staff' => $this->staff($user),
            'support_label' => $ticket->getDepartment(),
            'allowance' => $this->support->attachments(),
            'help' => $this->support->helpEnabled(),
        ]);
    }

    #[Route('/{id}/reply', name: 'support_ticket_reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(#[CurrentUser] User $user, Request $request, int $id): Response
    {
        $ticket = $this->found($this->tickets->reachable($id, $this->owner($user)));
        $refusal = $this->service->reply(
            $ticket,
            $user,
            $this->staff($user),
            (string) $request->request->get('body'),
            $request->files->all('files'),
        );

        $this->addFlash(...null === $refusal ? ['success', t('support.flash.sent')] : ['error', $refusal->reason]);

        return $this->redirectToRoute('support_ticket', ['id' => $id]);
    }

    #[Route('/{id}/close', name: 'support_ticket_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function close(#[CurrentUser] User $user, int $id): Response
    {
        $this->service->close($this->found($this->tickets->reachable($id, $this->owner($user))));
        $this->addFlash('success', t('support.flash.closed'));

        return $this->redirectToRoute('support_tickets');
    }

    #[Route('/{id}/drop', name: 'support_ticket_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(#[CurrentUser] User $user, int $id): Response
    {
        if (!$this->staff($user)) {
            throw $this->createAccessDeniedException();
        }

        $this->service->drop($this->found($this->tickets->conversation($id, null)));
        $this->addFlash('success', t('support.flash.ticket_dropped', ['number' => $id]));

        return $this->redirectToRoute('support_tickets');
    }

    #[Route('/message/{id}/{action}', name: 'support_message', requirements: ['id' => '\d+', 'action' => 'edit|drop|redact'], methods: ['POST'])]
    public function message(#[CurrentUser] User $user, Request $request, int $id, string $action): Response
    {
        if (!$this->staff($user)) {
            throw $this->createAccessDeniedException();
        }

        $message = $this->messages->find($id);
        if (null === $message) {
            throw $this->createNotFoundException();
        }

        $ticket = $message->getTicket();
        $body = (string) $request->request->get('body');
        $dropped = array_values(array_map(intval(...), (array) $request->request->all('dropped')));
        $refusal = match ($action) {
            'drop' => $this->service->remove($message),
            'edit' => $this->service->edit($message, $body, $request->files->all('files'), $dropped),
            default => $this->service->redact($message, $body, $dropped),
        };

        $this->addFlash(...null === $refusal
            ? ['success', match ($action) {
                'drop' => t('support.flash.dropped'),
                'edit' => t('support.flash.edited'),
                default => t('support.flash.redacted'),
            }]
            : ['error', $refusal->reason]);

        return $this->redirectToRoute('support_ticket', ['id' => $ticket->getId()]);
    }

    #[Route('/attachment/{id}', name: 'support_attachment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function attachment(#[CurrentUser] User $user, int $id): Response
    {
        $attachment = $this->attachments->find($id);
        if (!$attachment instanceof TicketAttachment) {
            throw $this->createNotFoundException();
        }

        $this->found($this->tickets->reachable(
            (int) $attachment->getMessage()->getTicket()->getId(),
            $this->owner($user),
        ));

        return $this->files->response($attachment);
    }

    private function found(?Ticket $ticket): Ticket
    {
        if (null === $ticket) {
            throw $this->createNotFoundException();
        }

        return $ticket;
    }

    private function owner(User $user): ?User
    {
        return $this->staff($user) ? null : $user;
    }

    private function staff(User $user): bool
    {
        return Role::Admin === $user->getRole();
    }
}
