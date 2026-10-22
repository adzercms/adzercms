<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Controller\Admin;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Intake;
use AdzerCMS\Leads\LeadRows;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/leads')]
#[IsGranted(Role::ADMIN)]
final class LeadController extends AbstractController
{
    private const LIST = 'admin_leads';

    public function __construct(
        private readonly LeadRows $rows,
        private readonly Intake $intake,
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
}
