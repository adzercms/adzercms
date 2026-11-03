<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\ModuleSources;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Entity\ModuleSource;
use AdzerCMS\Core\Module\Sources;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/admin/settings/sources')]
#[IsGranted(Role::ADMIN)]
final class SourceController extends AbstractController
{
    public function __construct(private readonly Sources $sources, private readonly Artifacts $artifacts)
    {
    }

    #[Route('/new', name: 'admin_source_new', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('@Admin/admin/_source_new.html.twig');
        }

        $refusal = $this->sources->add($request->request->getString('url'));
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('admin.sources.added'));

        return $this->page();
    }

    #[Route('/zip', name: 'admin_source_zip', methods: ['GET', 'POST'])]
    public function zip(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('@Admin/admin/_source_zip.html.twig');
        }

        $file = $request->files->get('archive');
        $refusal = $file instanceof UploadedFile ? $this->artifacts->upload($file) : new Refusal(t('core.module.zip.unreadable'));
        $this->addFlash(null === $refusal ? 'success' : 'error', $refusal->reason ?? t('admin.sources.uploaded'));

        return $this->page();
    }

    #[Route('/zip/drop', name: 'admin_source_zip_drop', methods: ['POST'])]
    public function zipDrop(Request $request): Response
    {
        $this->artifacts->drop($request->request->getString('package'), $request->request->getString('version'));
        $this->addFlash('success', t('admin.sources.dropped'));

        return $this->page();
    }

    #[Route('/{id}/credentials', name: 'admin_source_credentials', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function credentials(ModuleSource $source, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if ($request->request->getBoolean('drop')) {
                $this->sources->revoke($source);
                $this->addFlash('success', t('admin.credentials.cleared'));
            } else {
                $this->sources->grant($source, $request->request->getString('login'), $request->request->getString('token'));
                $this->addFlash('success', t('admin.credentials.saved'));
            }

            return $this->page();
        }

        return $this->render('@Theme/_credentials.html.twig', [
            'action' => $this->generateUrl('admin_source_credentials', ['id' => $source->getId()]),
            'fields' => [
                ['name' => 'login', 'label' => t('admin.sources.login'), 'secret' => false, 'value' => $source->getLogin()],
                ['name' => 'token', 'label' => t('admin.sources.token'), 'secret' => true, 'stored' => null !== $source->getTokenCipher()],
            ],
            'clearable' => null !== $source->getTokenCipher() || null !== $source->getLogin(),
        ]);
    }

    #[Route('/{id}/drop', name: 'admin_source_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function drop(ModuleSource $source): Response
    {
        $this->sources->drop($source);
        $this->addFlash('success', t('admin.sources.dropped'));

        return $this->page();
    }

    private function page(): RedirectResponse
    {
        return $this->redirectToRoute('admin_settings', ['page' => ModuleSources::PAGE->value]);
    }
}
