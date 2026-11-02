<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Contracts\Ui\Tabs;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\PageTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/references')]
#[IsGranted(Role::ADMIN)]
final class ReferencesController extends AbstractController
{
    #[Route('', name: 'admin_references', methods: ['GET'])]
    public function references(Request $request, PageTabs $pages): Response
    {
        $tabs = $pages->of(Tabs::ADMIN_REFERENCES, null);
        $asked = $request->query->getString('tab');
        $tab = isset($tabs[$asked]) ? $asked : (string) array_key_first($tabs);
        $fragment = $pages->render($tabs, $tab, null, $request, ['tab' => $tab]) ?? throw $this->createNotFoundException();

        if ($request->headers->has('HX-Request')) {
            return $this->render($fragment['template'], $fragment['context']);
        }

        return $this->render('@Admin/admin/references.html.twig', ['tabs' => $tabs, 'tab' => $tab, 'fragment' => $fragment]);
    }
}
