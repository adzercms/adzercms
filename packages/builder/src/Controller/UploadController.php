<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\ProjectUpload;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Pages\Controller\Admin\SiteController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[IsGranted(Role::ADMIN)]
final class UploadController extends AbstractController
{
    public const UPLOAD = 'builder_site_upload';
    public const FILE = 'file';

    public function __construct(private readonly ProjectUpload $upload)
    {
    }

    #[Route('/admin/pages/upload', name: self::UPLOAD, methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('@Builder/admin/_upload.html.twig');
        }

        $file = $request->files->get(self::FILE);
        $outcome = $file instanceof UploadedFile && $file->isValid() ? $this->upload->upload($file) : new Refusal(t('builder.refusal.file'));
        if ($outcome instanceof Refusal) {
            $this->addFlash('error', $outcome->reason);
        } else {
            $this->addFlash('success', t('builder.uploaded', ['count' => \count($outcome)]));
        }

        return $this->redirectToRoute(SiteController::LIST);
    }
}
