<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\PageTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(Role::ADMIN)]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, UserRepository $users, PageTabs $pages): Response
    {
        $tabs = $pages->of(Tabs::ADMIN_DASHBOARD, null);
        $asked = $request->query->getString('tab');
        $tab = isset($tabs[$asked]) ? $asked : array_key_first($tabs);
        $fragment = null === $tab ? null : $pages->render($tabs, $tab, null, $request, ['tab' => $tab]);

        if (null !== $fragment && $request->headers->has('HX-Request')) {
            return $this->render('@Admin/admin/_dashboard_tab.html.twig', ['fragment' => $fragment, 'counts' => $users->counts()]);
        }

        return $this->render('@Admin/admin/dashboard.html.twig', [
            'counts' => $users->counts(),
            'tabs' => $tabs,
            'tab' => $tab,
            'fragment' => $fragment,
        ]);
    }
}
