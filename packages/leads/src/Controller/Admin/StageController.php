<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Controller\Admin;

use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Leads\Stages;
use AdzerCMS\Leads\StageTab;
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
    public function __construct(
        private readonly Stages $service,
        private readonly StageTab $tab,
    ) {
    }

    #[Route('/order', name: 'admin_lead_stages_order', methods: ['POST'])]
    public function order(Request $request): Response
    {
        $this->service->arrange(Order::moved($request));

        return $this->render(StageTab::TEMPLATE, $this->tab->listing($request));
    }

    #[Route('/new', name: 'admin_lead_stage_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $refusal = null;

        if ($request->isMethod('POST')) {
            $outcome = $this->service->add(StageGroup::tryFrom($request->request->getString('group')), $request->request->getString('label'));

            if ($outcome instanceof Stage) {
                $this->addFlash('success', t('leads.flash.stage_added', ['label' => (string) $outcome->getLabel()]));

                return Fragment::go($this->list());
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

        return $this->redirect($this->list());
    }

    private function list(): string
    {
        return $this->generateUrl(AdminEntry::References->route(), ['tab' => StageTab::KEY]);
    }
}
