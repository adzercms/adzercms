<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Controller;

use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\ProjectAlbums;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/builder/versions')]
final class VersionController extends AbstractController
{
    public const PICTURE = 'builder_version_picture';

    public function __construct(
        private readonly ProjectVersionRepository $versions,
        private readonly ProjectAlbums $albums,
    ) {
    }

    #[Route('/{id}/pictures/{file}', name: self::PICTURE, requirements: ['id' => '\d+', 'file' => '[^/]+'], methods: ['GET'])]
    public function picture(int $id, string $file): Response
    {
        return new BinaryFileResponse($this->albums->ofVersion($this->seen($id))->path($file) ?? throw $this->createNotFoundException(), autoEtag: true);
    }

    private function seen(int $id): ProjectVersion
    {
        $version = $this->versions->find($id);
        if (null === $version || $version->getProject()->getOwner() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        return $version;
    }
}
