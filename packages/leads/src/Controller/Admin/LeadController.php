<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Controller\Admin;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Handling;
use AdzerCMS\Leads\Intake;
use AdzerCMS\Leads\LeadPage;
use AdzerCMS\Leads\LeadRows;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/leads')]
#[IsGranted(Role::ADMIN)]
final class LeadController extends AbstractController
{
    private const LIST = 'admin_leads';
    private const LEAD = 'admin_lead';

    public function __construct(
        private readonly LeadRows $rows,
        private readonly Intake $intake,
        private readonly LeadPage $page,
        private readonly Handling $handling,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $context = $this->rows->listing($request, self::LIST);

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Leads/admin/_leads.html.twig', $context);
        }

        return $this->render('@Leads/admin/leads.html.twig', $context);
    }

    #[Route('/new', name: 'admin_lead_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $refusals = [];

        if ($request->isMethod('POST')) {
            $outcome = $this->intake->add($request->request);

            if ($outcome instanceof Lead) {
                $this->addFlash('success', t('leads.flash.added', ['number' => $outcome->getId()]));

                return Fragment::go($this->generateUrl(self::LIST));
            }
            $refusals = $outcome;
        }

        return $this->render('@Leads/admin/_new.html.twig', ['input' => $request->request->all(), 'refusals' => $refusals]);
    }

    #[Route('/{id}', name: self::LEAD, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Lead $lead): Response
    {
        return $this->card($lead, [], null);
    }

    #[Route('/{id}', name: 'admin_lead_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(Lead $lead, #[CurrentUser] User $user, Request $request): Response
    {
        $refusals = $this->handling->save($lead, $user, $request->request);
        if ([] !== $refusals) {
            return $this->card($lead, $refusals, $request->request->all());
        }
        $this->addFlash('success', t('leads.flash.saved', ['number' => $lead->getId()]));

        return $this->redirectToRoute(self::LEAD, ['id' => $lead->getId()]);
    }

    #[Route('/{id}/note', name: 'admin_lead_note', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function note(Lead $lead, #[CurrentUser] User $user, Request $request): Response
    {
        $refusal = $this->handling->note($lead, $user, $request->request->getString(Handling::NOTE));
        $this->addFlash(...null === $refusal ? ['success', t('leads.flash.noted')] : ['error', $refusal->reason]);

        return $this->redirectToRoute(self::LEAD, ['id' => $lead->getId()]);
    }

    #[Route('/{id}/drop', name: 'admin_lead_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(Lead $lead): Response
    {
        $number = $lead->getId();
        $this->em->remove($lead);
        $this->em->flush();
        $this->addFlash('success', t('leads.flash.dropped', ['number' => $number]));

        return $this->redirectToRoute(self::LIST);
    }

    /**
     * @param array<string, Refusal>    $refusals
     * @param array<string, mixed>|null $input
     */
    private function card(Lead $lead, array $refusals, ?array $input): Response
    {
        return $this->render('@Leads/admin/lead.html.twig', $this->page->context($lead, $refusals, $input), new Response(status: [] === $refusals ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
