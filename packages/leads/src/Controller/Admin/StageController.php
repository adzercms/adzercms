<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Controller\Admin;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Leads\Stages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/leads/stages')]
#[IsGranted(Role::ADMIN)]
final class StageController extends AbstractController
{
    private const LIST = 'admin_lead_stages';

    public function __construct(
        private readonly StageRepository $stages,
        private readonly Stages $service,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        $context = $this->listing($request);

        if ($request->headers->has('HX-Request')) {
            return $this->render('@Leads/admin/_stages.html.twig', $context);
        }

        return $this->render('@Leads/admin/stages.html.twig', $context);
    }

    #[Route('/order', name: 'admin_lead_stages_order', methods: ['POST'])]
    public function order(Request $request): Response
    {
        $this->service->arrange(Order::moved($request));

        return $this->render('@Leads/admin/_stages.html.twig', $this->listing($request));
    }

    #[Route('/new', name: 'admin_lead_stage_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $refusal = null;

        if ($request->isMethod('POST')) {
            $outcome = $this->service->add(StageGroup::tryFrom($request->request->getString('group')), $request->request->getString('label'));

            if ($outcome instanceof Stage) {
                $this->addFlash('success', t('leads.flash.stage_added', ['label' => (string) $outcome->getLabel()]));

                return Fragment::go($this->generateUrl(self::LIST));
            }
            $refusal = $outcome;
        }

        return $this->render('@Leads/admin/_stage.html.twig', [
            'groups' => StageGroup::cases(),
            'input' => $request->request->all(),
            'error' => $refusal?->reason,
        ]);
    }

    #[Route('/{id}/drop', name: 'admin_lead_stage_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(Stage $stage): Response
    {
        $refusal = $this->service->drop($stage);
        $this->addFlash(...null === $refusal ? ['success', t('leads.flash.stage_dropped')] : ['error', $refusal->reason]);

        return $this->redirectToRoute(self::LIST);
    }

    /** @return array<string, mixed> */
    private function listing(Request $request): array
    {
        $table = Table::of($request, self::LIST, [
            Column::plain('label', t('leads.stage.title')),
            Column::plain('group', t('leads.stage.group')),
            Column::plain('actions'),
        ], 'label', t('leads.stage.empty'), order: Order::of('admin_lead_stages_order', 's.position', 's.id'));

        return ['table' => $table, 'rows' => $table->rows($this->stages->funnel())];
    }
}
